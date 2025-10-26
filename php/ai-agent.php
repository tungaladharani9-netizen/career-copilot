<?php
// ai-agent.php - Handle AI requests
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $user_id = $input['user_id'] ?? 0;
    $job_description = sanitize_input($input['job_description'] ?? '');
    $count = isset($input['count']) && is_numeric($input['count']) ? max(1, min(20, intval($input['count']))) : 10;
    
    if (empty($job_description)) {
        echo json_encode([
            'success' => false,
            'message' => 'Job description is required'
        ]);
        exit;
    }

function normalizeQuestion($q) {
    $q = strip_tags($q);
    // remove content in parentheses like (in context of X)
    $q = preg_replace('/\s*\([^\)]*\)\s*/', ' ', $q);
    // lowercase
    $q = mb_strtolower($q);
    // remove punctuation
    $q = preg_replace('/[\p{P}\p{S}]+/u', ' ', $q);
    // collapse whitespace
    $q = preg_replace('/\s+/', ' ', trim($q));
    return $q;
}

function uniqueQuestions($list, $count) {
    $seen = [];
    $out = [];
    foreach ($list as $item) {
        $q = is_array($item) && isset($item['question']) ? trim($item['question']) : '';
        if ($q === '') { continue; }
        $key = normalizeQuestion($q);
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $out[] = $item;
            if (count($out) >= $count) break;
        }
    }
    // Enforce count cap
    if (count($out) > $count) {
        $out = array_slice($out, 0, $count);
    }
    return $out;
}
    if (mb_strlen($job_description) < 50) {
        echo json_encode([
            'success' => false,
            'message' => 'Please provide a more detailed job description (at least 50 characters)'
        ]);
        exit;
    }
    
    // Store job description
    $stmt = $conn->prepare("INSERT INTO job_descriptions (user_id, job_description) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $job_description);
    $stmt->execute();
    $job_id = $conn->insert_id;
    
    // Auto-select provider based on environment variables
    $openaiKey = getenv('OPENAI_API_KEY') ?: (defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '');
    $anthropicKey = getenv('ANTHROPIC_API_KEY') ?: (defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : '');
    if (!empty($openaiKey)) {
        $questions = callOpenAI($job_description, $openaiKey, $count);
    } elseif (!empty($anthropicKey)) {
        $questions = callClaudeAPI($job_description, $anthropicKey, $count);
    } else {
        // Fallback to mock data
        $questions = generateMockQuestions($job_description, $count);
    }

    // Ensure valid structure or fallback to mock
    if (!is_array($questions) || empty($questions)) {
        $questions = generateMockQuestions($job_description, $count);
    }
    $questions = array_merge([
        'fresher' => [],
        'experienced' => [],
        'behavioral' => [],
        'grooming' => []
    ], $questions);

    // Dedupe and enforce count per category
    $questions['fresher'] = uniqueQuestions($questions['fresher'] ?? [], $count);
    $questions['experienced'] = uniqueQuestions($questions['experienced'] ?? [], $count);
    $questions['behavioral'] = uniqueQuestions($questions['behavioral'] ?? [], $count);
    // Grooming are strings; ensure uniqueness and count
    if (isset($questions['grooming']) && is_array($questions['grooming'])) {
        $questions['grooming'] = array_values(array_unique($questions['grooming']));
        $questions['grooming'] = array_slice($questions['grooming'], 0, $count);
    }
    
    // Store questions in database
    foreach ($questions['fresher'] as $q) {
        $stmt = $conn->prepare("INSERT INTO interview_questions (job_description_id, question_type, question, answer) VALUES (?, 'fresher', ?, ?)");
        $stmt->bind_param("iss", $job_id, $q['question'], $q['answer']);
        $stmt->execute();
    }
    
    foreach ($questions['experienced'] as $q) {
        $stmt = $conn->prepare("INSERT INTO interview_questions (job_description_id, question_type, question, answer) VALUES (?, 'experienced', ?, ?)");
        $stmt->bind_param("iss", $job_id, $q['question'], $q['answer']);
        $stmt->execute();
    }
    
    foreach ($questions['behavioral'] as $q) {
        $stmt = $conn->prepare("INSERT INTO interview_questions (job_description_id, question_type, question, answer) VALUES (?, 'behavioral', ?, ?)");
        $stmt->bind_param("iss", $job_id, $q['question'], $q['answer']);
        $stmt->execute();
    }
    
    foreach ($questions['grooming'] as $tip) {
        $stmt = $conn->prepare("INSERT INTO interview_questions (job_description_id, question_type, question, answer) VALUES (?, 'grooming', ?, '')");
        $stmt->bind_param("is", $job_id, $tip);
        $stmt->execute();
    }
    
    echo json_encode([
        'success' => true,
        'data' => $questions
    ]);
}

// Function to call OpenAI API
function callOpenAI($jobDescription, $apiKey, $count) {
    $url = 'https://api.openai.com/v1/chat/completions';
    
    $prompt = "Analyze this job description and generate:\n
    1. {$count} technical interview questions for freshers with detailed answers\n
    2. {$count} technical interview questions for experienced candidates with detailed answers\n
    3. {$count} behavioral interview questions with sample answers using STAR method\n
    4. {$count} grooming and professional etiquette tips\n\n
    Job Description: $jobDescription\n\n
    Return response in JSON format with keys: fresher, experienced, behavioral, grooming";
    
    $data = [
        'model' => (defined('OPENAI_MODEL') ? OPENAI_MODEL : 'gpt-3.5-turbo'),
        'messages' => [
            ['role' => 'system', 'content' => 'You are an expert career counselor and interview coach.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.9
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    $response = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false || $http < 200 || $http >= 300) {
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    $result = json_decode($response, true);
    if (!is_array($result) || !isset($result['choices'][0]['message']['content'])) {
        return null;
    }
    $content = $result['choices'][0]['message']['content'];
    $parsed = json_decode($content, true);
    if (!is_array($parsed)) {
        return null;
    }
    return $parsed;
}

// Function to call Claude API
function callClaudeAPI($jobDescription, $apiKey, $count) {
    $url = 'https://api.anthropic.com/v1/messages';
    
    $prompt = "Analyze this job description and generate:\n
    1. {$count} technical interview questions for freshers with detailed answers\n
    2. {$count} technical interview questions for experienced candidates with detailed answers\n
    3. {$count} behavioral interview questions with sample answers using STAR method\n
    4. {$count} grooming and professional etiquette tips\n\n
    Job Description: $jobDescription\n\n
    Return response in JSON format with keys: fresher, experienced, behavioral, grooming";
    
    $data = [
        'model' => 'claude-3-sonnet-20240229',
        'max_tokens' => 4096,
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01'
    ]);
    
    $response = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false || $http < 200 || $http >= 300) {
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    $result = json_decode($response, true);
    if (!is_array($result) || !isset($result['content'][0]['text'])) {
        return null;
    }
    $parsed = json_decode($result['content'][0]['text'], true);
    if (!is_array($parsed)) {
        return null;
    }
    return $parsed;
}

// Mock function for testing (Replace with actual AI API in production)
function generateMockQuestions($jobDescription, $count) {
    // Extract key skills from job description
    $skills = extractSkills($jobDescription);
    
    // Shuffle skills for variation
    if (!empty($skills)) {
        shuffle($skills);
    }

    $base = [
        'fresher' => [
            [
                'question' => 'What are the fundamental concepts you need to understand for this role?',
                'answer' => 'For this position, you should understand: ' . implode(', ', array_slice($skills, 0, 3)) . '. Start by explaining basic concepts, show your learning attitude, and demonstrate how you\'ve applied these in academic projects or self-learning.'
            ],
            [
                'question' => 'Can you explain a project where you used ' . ($skills[0] ?? 'relevant technology') . '?',
                'answer' => 'Structure your answer using: 1) Project overview, 2) Your specific role, 3) Technologies used, 4) Challenges faced, 5) Solutions implemented, 6) Results achieved. Even if it\'s an academic project, focus on the technical implementation and what you learned.'
            ],
            [
                'question' => 'How do you approach learning new technologies?',
                'answer' => 'Describe your learning methodology: 1) Official documentation review, 2) Hands-on practice with small projects, 3) Online courses or tutorials, 4) Community engagement (Stack Overflow, GitHub), 5) Building practical applications to solidify understanding.'
            ],
            [
                'question' => 'What interests you about this role and our company?',
                'answer' => 'Research the company beforehand. Mention: 1) Specific products/services that excite you, 2) Company culture alignment, 3) Growth opportunities, 4) How your skills match the role requirements, 5) Your career goals alignment with the company\'s direction.'
            ],
            [
                'question' => 'Describe a challenging problem you solved recently.',
                'answer' => 'Use the STAR method: Situation (context), Task (what needed to be done), Action (steps you took), Result (outcome). Focus on your problem-solving approach, research methods, and persistence even for academic/personal projects.'
            ]
        ],
        'experienced' => [
            [
                'question' => 'How have you implemented ' . ($skills[0] ?? 'key technology') . ' in production environments?',
                'answer' => 'Discuss: 1) Scale of implementation (users, data volume), 2) Architecture decisions and trade-offs, 3) Performance optimizations, 4) Monitoring and maintenance, 5) Team collaboration, 6) Business impact. Quantify results with metrics when possible.'
            ],
            [
                'question' => 'Describe your experience with system design and scalability.',
                'answer' => 'Cover: 1) Load balancing strategies, 2) Database optimization (indexing, caching), 3) Microservices vs monolithic decisions, 4) API design principles, 5) CDN usage, 6) Horizontal vs vertical scaling choices. Provide specific examples from past projects.'
            ],
            [
                'question' => 'How do you ensure code quality and maintainability?',
                'answer' => 'Explain your practices: 1) Code review processes, 2) Testing strategies (unit, integration, E2E), 3) CI/CD pipelines, 4) Design patterns usage, 5) Documentation standards, 6) Refactoring approaches, 7) Technical debt management.'
            ],
            [
                'question' => 'Tell me about a time you mentored junior developers.',
                'answer' => 'Highlight: 1) Knowledge transfer methods (pair programming, code reviews), 2) Creating learning resources, 3) Setting up best practices, 4) Regular 1-on-1s, 5) Measuring progress, 6) Impact on team productivity and individual growth.'
            ],
            [
                'question' => 'How do you handle technical disagreements with team members?',
                'answer' => 'Demonstrate maturity: 1) Listen actively to understand perspectives, 2) Present data/metrics to support viewpoints, 3) Consider trade-offs objectively, 4) Prototype/POC when needed, 5) Focus on project goals over ego, 6) Document decisions for future reference.'
            ]
        ],
        'behavioral' => [
            [
                'question' => 'Tell me about a time you failed at something.',
                'answer' => 'Be honest and show growth: 1) Describe the situation clearly, 2) Explain what went wrong, 3) Take accountability, 4) Detail lessons learned, 5) Show how you applied those lessons later, 6) Demonstrate resilience and growth mindset.'
            ],
            [
                'question' => 'Describe a situation where you had to work under pressure.',
                'answer' => 'Use STAR method: 1) Set the context (deadline, stakes), 2) Explain the pressure points, 3) Describe your approach (prioritization, time management), 4) Highlight collaboration if applicable, 5) Share positive outcomes, 6) Reflect on stress management techniques used.'
            ],
            [
                'question' => 'How do you handle constructive criticism?',
                'answer' => 'Show maturity: 1) Express appreciation for feedback, 2) Ask clarifying questions, 3) Reflect objectively without being defensive, 4) Create action plan for improvement, 5) Follow up on progress, 6) Give examples of how past feedback helped you grow.'
            ],
            [
                'question' => 'Describe your ideal work environment.',
                'answer' => 'Balance personal preferences with adaptability: 1) Collaborative yet focused atmosphere, 2) Clear communication channels, 3) Opportunities for learning, 4) Work-life balance, 5) Recognition and feedback culture, 6) Show flexibility and adaptability to various environments.'
            ],
            [
                'question' => 'Where do you see yourself in 5 years?',
                'answer' => 'Show ambition aligned with company: 1) Technical skills you want to develop, 2) Leadership or specialized expertise goals, 3) Impact you want to make, 4) Continuous learning commitment, 5) Balance ambition with realistic growth path, 6) Align with company\'s trajectory when possible.'
            ]
        ],
        'grooming' => [
            'Professional Attire: Dress one level above the company dress code. For tech companies, smart casual is often appropriate. Ensure clothes are clean, ironed, and fit well. Conservative colors like navy, gray, or black project professionalism.',
            'Body Language: Maintain good posture, offer a firm handshake, make appropriate eye contact (60-70% of the time), avoid fidgeting, and smile genuinely. Nod occasionally to show engagement.',
            'Communication Skills: Speak clearly and at a moderate pace, avoid filler words (um, like, you know), listen actively, don\'t interrupt, and ask thoughtful questions. Practice your responses but don\'t sound rehearsed.',
            'Punctuality & Preparation: Arrive 10-15 minutes early, bring extra copies of your resume, have a notepad and pen, research the company thoroughly, prepare questions to ask, and know the interviewer\'s names and positions.',
            'Follow-up Etiquette: Send a thank-you email within 24 hours, reference specific discussion points, reiterate your interest, keep it concise (3-4 paragraphs), proofread carefully, and follow up on timeline if you don\'t hear back within the specified period.'
        ]
    ];

    // Randomize base order for uniqueness per request
    foreach (['fresher','experienced','behavioral','grooming'] as $k) {
        if (isset($base[$k]) && is_array($base[$k])) {
            shuffle($base[$k]);
        }
    }

    $expanded = [];
    $expanded['fresher'] = expandListWithIndex($base['fresher'], $count, $skills);
    $expanded['experienced'] = expandListWithIndex($base['experienced'], $count, $skills);
    $expanded['behavioral'] = expandListWithIndex($base['behavioral'], $count, $skills);
    $expanded['grooming'] = expandListPlain($base['grooming'], $count);
    return $expanded;
}

function expandListWithIndex($items, $count, $skills = []) {
    $out = [];
    $n = count($items);
    if ($n === 0) return $out;
    // If asked count is within base length, return unique items only (no repetition)
    if ($count <= $n) {
        for ($i = 0; $i < $count; $i++) {
            $tpl = $items[$i];
            $skillContext = (!empty($skills)) ? $skills[$i % count($skills)] : '';
            $q = $tpl['question'];
            if ($skillContext) { $q = preg_replace('/\?$/', '', $q) . " (in context of $skillContext)?"; }
            $a = $tpl['answer'];
            if ($skillContext) { $a .= " Consider highlighting your experience with $skillContext when relevant."; }
            $out[] = ['question' => $q, 'answer' => $a];
        }
        return $out;
    }
    // Otherwise, cycle through
    for ($i = 0; $i < $count; $i++) {
        $tpl = $items[$i % $n];
        $skillContext = (!empty($skills)) ? $skills[$i % count($skills)] : '';
        $q = $tpl['question'];
        if ($skillContext) { $q = preg_replace('/\?$/', '', $q) . " (in context of $skillContext)?"; }
        $a = $tpl['answer'];
        if ($skillContext) { $a .= " Consider highlighting your experience with $skillContext when relevant."; }
        $out[] = ['question' => $q, 'answer' => $a];
    }
    return $out;
}

function expandListPlain($items, $count) {
    $out = [];
    $n = count($items);
    for ($i = 0; $i < $count; $i++) {
        $out[] = $items[$i % $n];
    }
    return $out;
}

function extractSkills($text) {
    $commonSkills = ['Python', 'JavaScript', 'Java', 'React', 'Node.js', 'SQL', 'AWS', 'Docker', 'Git', 'API', 'REST', 'Agile', 'MongoDB', 'Angular', 'Vue.js'];
    $found = [];
    
    foreach ($commonSkills as $skill) {
        if (stripos($text, $skill) !== false) {
            $found[] = $skill;
        }
    }
    
    return !empty($found) ? $found : ['programming', 'software development', 'problem-solving'];
}

$conn->close();
?>