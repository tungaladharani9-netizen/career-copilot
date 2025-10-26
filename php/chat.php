<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$user_id = intval($input['user_id'] ?? 0);
$message = sanitize_input($input['message'] ?? '');
$job_description = sanitize_input($input['job_description'] ?? '');
$context = sanitize_input($input['context'] ?? '');

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Message is required']);
    exit;
}

// Pull latest job description and some recent questions for context
$latestJD = '';
$recentQA = [];
if ($user_id > 0) {
    $stmt = $conn->prepare("SELECT id, job_description FROM job_descriptions WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $latestJD = $row['job_description'];
        $jdId = intval($row['id']);
        // recent questions
        $qStmt = $conn->prepare("SELECT question_type, question, answer FROM interview_questions WHERE job_description_id = ? ORDER BY id DESC LIMIT 12");
        $qStmt->bind_param("i", $jdId);
        $qStmt->execute();
        $qRes = $qStmt->get_result();
        $recentQA = [];
        while ($q = $qRes->fetch_assoc()) { $recentQA[] = $q; }
    }
}

// Prefer skills detected in the user's message; then enrich with JD/context
$skillsMsg = extractSkillsForChat($message);
$skillsCtx = extractSkillsForChat(($job_description ?: $latestJD) . ' ' . $context);
$skills = array_values(array_unique(array_merge($skillsMsg, $skillsCtx)));

$openaiKey = getenv('OPENAI_API_KEY') ?: (defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '');
if (!empty($openaiKey)) {
    // Build messages
    $messages = [];
    $messages[] = ['role' => 'system', 'content' => 'You are Career Copilot, a helpful interview coach. Provide accurate, concise, and actionable answers. If asked for examples, include one.'];
    $messages[] = ['role' => 'system', 'content' => 'Job Description Context: ' . ($job_description ?: $latestJD ?: 'N/A')];
    if (!empty($recentQA)) {
        $ctx = "Recent Q&A: \n";
        foreach ($recentQA as $i => $qa) {
            $ctx .= ($i+1) . ") [" . $qa['question_type'] . "] Q: " . $qa['question'] . "\nA: " . $qa['answer'] . "\n";
        }
        $messages[] = ['role' => 'system', 'content' => $ctx];
    }
    if (!empty($skills)) {
        $messages[] = ['role' => 'system', 'content' => 'Relevant skills: ' . implode(', ', $skills)];
    }
    $messages[] = ['role' => 'user', 'content' => $message];

    $payload = [
        'model' => (defined('OPENAI_MODEL') ? OPENAI_MODEL : 'gpt-3.5-turbo'),
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 500
    ];

    $endpoint = 'https://api.openai.com/v1/chat/completions';
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openaiKey
    ];

    $tryModels = [(defined('OPENAI_MODEL') ? OPENAI_MODEL : 'gpt-3.5-turbo'), 'gpt-3.5-turbo', 'gpt-3.5-turbo-0125'];
    $lastError = '';
    foreach ($tryModels as $m) {
        $payload['model'] = $m;
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $resp = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);
        if ($curlErr) { $lastError = 'OpenAI request failed: ' . $curlErr; continue; }
        $data = json_decode($resp, true);
        if (isset($data['error'])) { $lastError = 'OpenAI API error: ' . ((is_array($data['error']) && isset($data['error']['message'])) ? $data['error']['message'] : json_encode($data['error'])); continue; }
        $reply = $data['choices'][0]['message']['content'] ?? null;
        if ($reply) {
            echo json_encode(['success' => true, 'reply' => $reply]);
            exit;
        }
        $lastError = 'No reply returned from model ' . $m;
    }
    echo json_encode(['success' => false, 'message' => $lastError ?: 'OpenAI request failed']);
    exit;
    if ($reply) {
        echo json_encode(['success' => true, 'reply' => $reply]);
        exit;
    }
}

// Fallback contextual mock (varied by intent)
$msg = strtolower($message);
// Detect high-level intents that should NOT tie to tech stack
$isBehavioral = preg_match('/\b(behavioral|star|tell me about yourself|tmay|conflict|challenge|leadership|team|strength|weakness|communication|introduce)\b/i', $message);
$isGeneral = preg_match('/\b(difference between|what is difference|compare|vs\.|versus|yourself)\b/i', $message);

// Only keep tech skill tie-ins when clearly technical
$primarySkill = (!($isBehavioral || $isGeneral) && !empty($skills)) ? $skills[0] : '';
// Avoid inserting unrelated recent question hints for behavioral/general
$recentHint = (!($isBehavioral || $isGeneral) && !empty($recentQA)) ? $recentQA[0]['question'] : '';

// Specific: "difference between X and Y"
if (preg_match('/difference between\s+(.+?)\s+(and|&|versus|vs\.?)+\s+(.+)/i', $message, $m)) {
    $x = trim($m[1]);
    $y = trim($m[3]);
    $reply = "Q: " . $message . "\n\n" .
             "Difference between {$x} and {$y}:\n" .
             "- Definition: briefly define each.\n" .
             "- Key distinction: what primarily sets them apart.\n" .
             "- When to use: scenario where {$x} is favored vs {$y}.\n" .
             "- Example: 1-line example for each.";
} elseif (preg_match('/method\s+overloading/i', $message)) {
    $reply = "Method Overloading (clear explanation):\n" .
             "- Definition: Having multiple methods with the same name in the same class but different parameter lists (count, types, or order).\n" .
             "- Purpose: Improves readability and flexibility by offering variants for different inputs.\n" .
             "- Resolved at: Compile-time (static polymorphism).\n\n" .
             "Java example:\n" .
             "class MathUtil {\n" .
             "  int add(int a, int b){ return a + b; }\n" .
             "  double add(double a, double b){ return a + b; }\n" .
             "  int add(int a, int b, int c){ return a + b + c; }\n" .
             "}\n" .
             "// Usage: new MathUtil().add(2,3); new MathUtil().add(2.0,3.5); new MathUtil().add(1,2,3);\n\n" .
             "Notes: Overloading differs from overriding (same signature in subclass, runtime binding).";
} elseif (preg_match('/^(what is|define|explain)\b/', $msg)) {
    $topic = trim(preg_replace('/^(what is|define|explain)\s*/', '', $message));
    $reply = 'Explanation outline for ' . ($topic ?: 'the topic') . ":\n" .
             "- Key idea: clarify purpose and when it's used.\n" .
             "- Core concepts: components, workflow, and trade-offs.\n" .
             ( $primarySkill ? "- Relate to your stack: tie back to {$primarySkill}.\n" : '' ) .
             '' .
             "- Example: provide a concise example and expected output.";
} elseif (preg_match('/\b(java)\b/i', $message) && preg_match('/\b(run|execute|compile|javac|class file)\b/i', $message)) {
    $reply = "Run Java code steps: \n" .
             "1) Install JDK (https://adoptium.net) and ensure 'javac'/'java' are in PATH.\n" .
             "2) Create HelloWorld.java:\n   public class HelloWorld { public static void main(String[] args){ System.out.println(\"Hello\"); } }\n" .
             "3) Compile: javac HelloWorld.java (produces HelloWorld.class).\n" .
             "4) Run: java HelloWorld (no .class extension).\n" .
             "5) If using packages, run from the project root and include the package name: java pkg.HelloWorld.\n" .
             "6) For libraries: use -cp to set classpath, e.g., java -cp .;lib/* Main (Windows) or java -cp .:lib/* Main (Linux/macOS).";
} elseif (preg_match('/how (do|to)|best practice|prepare|improve|optimi[sz]e/', $msg)) {
    $reply = "Practical steps: \n" .
             ( $primarySkill ? "1) Review {$primarySkill} fundamentals and common pitfalls.\n" : "1) Review the fundamentals and common pitfalls.\n" ) .
             "2) Practice with 2-3 small examples or katas.\n" .
             "3) Explain trade-offs (performance, readability, testing).\n" .
             "4) Validate with a small demo/test and iterate.";
} elseif (preg_match('/behavioral|star|tell me about a time|conflict|challenge/', $msg)) {
    $reply = "Use STAR: \n" .
             "- Situation: 1 sentence context.\n" .
             "- Task: your responsibility.\n" .
             "- Action: 2-3 concrete actions you took.\n" .
             "- Result: measurable outcome + learning.\n" .
             '';
} elseif (preg_match('/tell me about yourself|introduce yourself|self introduction|tmay/i', $message)) {
    $reply = "Tell me about yourself - structure: \n" .
             "- Present: role/skills and relevant strengths.\n" .
             "- Past: concise experience or education (impact).\n" .
             "- Future: why this role/company and what you aim to contribute.\n" .
             "- Keep it 60-90 seconds; avoid reading your resume line-by-line.";
} elseif (preg_match('/strength|strengh|strngth|weakness|weaknes/i', $message)) {
    $reply = "Strengths/Weaknesses guidance: \n" .
             "- Strength: pick 1-2 relevant to the role, add 1-line example.\n" .
             "  Example: 'Stakeholder communication — I led weekly syncs across 3 teams to align timelines, reducing blockers by 25%." . "'\n" .
             "- Weakness: a real but non-critical area + what you're doing to improve.\n" .
             "  Example: 'I tended to over-detail status updates; I now use a 3-bullet format and timebox prep, which keeps updates crisp.'\n" .
             "- Avoid cliches; keep it honest, concise, and job-aligned.";
} elseif (preg_match('/mock|practice|question|quiz|sample/', $msg)) {
    $q = $primarySkill ? "Explain a recent project where you applied {$primarySkill}. What trade-offs did you consider?" : "Walk me through a recent project decision. What trade-offs did you consider?";
    $reply = "Try this question: \n- " . $q . "\n\nHow to answer: context (1-2 lines), design/approach, trade-offs, result, and a small improvement you would make now.";
} else {
    // Generic intent: tailor using keywords and varied templates
    $words = preg_split('/[^a-z0-9]+/i', $message, -1, PREG_SPLIT_NO_EMPTY);
    $freq = [];
    foreach ($words as $w) {
        $lw = strtolower($w);
        if (strlen($lw) < 4) continue; // ignore very short words
        $freq[$lw] = ($freq[$lw] ?? 0) + 1;
    }
    arsort($freq);
    $top = array_slice(array_keys($freq), 0, 3);
    $kw = !empty($top) ? implode(', ', $top) : '';

    $variant = crc32($msg) % 4; // deterministic per message
    switch ($variant) {
        case 0:
            $reply = "Approach: \n" .
                     ( $kw ? "- Focus areas: {$kw}.\n" : '' ) .
                     ( $primarySkill ? "- Tie back to {$primarySkill} with an example.\n" : "- Provide a short example to illustrate.\n" ) .
                     "- Discuss trade-offs and an alternative.";
            break;
        case 1:
            $reply = "Checklist: \n" .
                     ( $kw ? "1) Clarify terms ({$kw}).\n" : "1) Clarify the core terms.\n" ) .
                     "2) Outline steps or architecture.\n" .
                     ( $primarySkill ? "3) Map to {$primarySkill} best practices.\n" : "3) Reference best practices.\n" ) .
                     "4) Give a concise example and outcome.";
            break;
        case 2:
            $reply = "Summary + Example: \n" .
                     ( $kw ? "- Topic keywords: {$kw}.\n" : '' ) .
                     "- One-sentence summary of the idea.\n" .
                     ( $primarySkill ? "- Mini example using {$primarySkill}.\n" : "- Mini example demonstrating the idea.\n" ) .
                     "- Mention pitfalls and how to avoid them.";
            break;
        default:
            $reply = "Pointers: \n" .
                     ( $primarySkill ? "- Start from {$primarySkill} fundamentals.\n" : "- Start from the fundamentals.\n" ) .
                     ( $kw ? "- Address: {$kw}.\n" : '' ) .
                     "- Compare two options and justify your choice.\n" .
                     "- End with an improvement or next step.";
            break;
    }
    // Make the reply visibly tied to the actual question
    $reply = "Q: " . $message . "\n\n" . $reply;
}

echo json_encode(['success' => true, 'reply' => $reply]);
exit;

function extractSkillsForChat($text) {
    $common = ['Python','JavaScript','Java','React','Node.js','SQL','AWS','Docker','Git','API','REST','Agile','MongoDB','Angular','Vue.js'];
    $found = [];
    foreach ($common as $s) {
        if (stripos($text, $s) !== false) { $found[] = $s; }
    }
    return array_values(array_unique($found));
}
