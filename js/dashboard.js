// dashboard.js - Dashboard and AI Agent Scripts

// Check if user is logged in
document.addEventListener('DOMContentLoaded', () => {
    const userId = localStorage.getItem('user_id');
    const userEmail = localStorage.getItem('user_email');
    
    if (!userId || !userEmail) {
        window.location.href = 'index.html';
        return;
    }
    
    document.getElementById('userEmail').textContent = userEmail;
});

// Show notification
function showNotification(message, type = 'success') {
    const notification = document.getElementById('notification');
    notification.textContent = message;
    notification.className = `notification ${type}`;
    notification.style.display = 'block';
    
    setTimeout(() => {
        notification.style.display = 'none';
    }, 4000);
}

// Logout function
function logout() {
    localStorage.removeItem('user_id');
    localStorage.removeItem('user_email');
    showNotification('Logged out successfully', 'success');
    setTimeout(() => {
        window.location.href = 'index.html';
    }, 1000);
}

// Generate interview questions
async function generateQuestions() {
    const jobDescription = document.getElementById('jobDescription').value.trim();
    
    if (!jobDescription) {
        showNotification('Please enter a job description', 'error');
        return;
    }
    
    if (jobDescription.length < 50) {
        showNotification('Please provide a more detailed job description (at least 50 characters)', 'error');
        return;
    }
    
    const generateBtn = document.getElementById('generateBtn');
    const loadingIndicator = document.getElementById('loadingIndicator');
    const resultsContainer = document.getElementById('resultsContainer');
    
    // Show loading
    generateBtn.disabled = true;
    generateBtn.textContent = 'Generating...';
    loadingIndicator.style.display = 'block';
    resultsContainer.style.display = 'none';
    
    try {
        const userId = localStorage.getItem('user_id');
        
        const response = await fetch('php/ai-agent.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: userId,
                job_description: jobDescription
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            displayResults(result.data);
            showNotification('Interview questions generated successfully!', 'success');
        } else {
            showNotification(result.message || 'Failed to generate questions', 'error');
        }
    } catch (error) {
        showNotification('Error generating questions. Please try again.', 'error');
        console.error('Error:', error);
    } finally {
        generateBtn.disabled = false;
        generateBtn.textContent = '🚀 Generate Interview Questions';
        loadingIndicator.style.display = 'none';
    }
}

// Display results
function displayResults(data) {
    const resultsContainer = document.getElementById('resultsContainer');
    const fresherQuestionsDiv = document.getElementById('fresherQuestions');
    const experiencedQuestionsDiv = document.getElementById('experiencedQuestions');
    const behavioralQuestionsDiv = document.getElementById('behavioralQuestions');
    const groomingTipsDiv = document.getElementById('groomingTips');
    
    // Clear previous results
    fresherQuestionsDiv.innerHTML = '';
    experiencedQuestionsDiv.innerHTML = '';
    behavioralQuestionsDiv.innerHTML = '';
    groomingTipsDiv.innerHTML = '';
    
    // Display Fresher Questions
    data.fresher.forEach((item, index) => {
        const questionDiv = createQuestionElement(index + 1, item.question, item.answer);
        fresherQuestionsDiv.appendChild(questionDiv);
    });
    
    // Display Experienced Questions
    data.experienced.forEach((item, index) => {
        const questionDiv = createQuestionElement(index + 1, item.question, item.answer);
        experiencedQuestionsDiv.appendChild(questionDiv);
    });
    
    // Display Behavioral Questions
    data.behavioral.forEach((item, index) => {
        const questionDiv = createQuestionElement(index + 1, item.question, item.answer);
        behavioralQuestionsDiv.appendChild(questionDiv);
    });
    
    // Display Grooming Tips
    data.grooming.forEach((tip, index) => {
        const tipDiv = document.createElement('div');
        tipDiv.className = 'question-item';
        tipDiv.innerHTML = `
            <h4>💎 Tip ${index + 1}</h4>
            <p>${tip}</p>
        `;
        groomingTipsDiv.appendChild(tipDiv);
    });
    
    // Show results with smooth scroll
    resultsContainer.style.display = 'block';
    resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Create question element
function createQuestionElement(number, question, answer) {
    const div = document.createElement('div');
    div.className = 'question-item';
    div.innerHTML = `
        <h4>Q${number}. ${question}</h4>
        <p><strong>Answer:</strong> ${answer}</p>
    `;
    return div;
}

// Add keyboard shortcut for generating questions (Ctrl/Cmd + Enter)
document.getElementById('jobDescription').addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        generateQuestions();
    }
});

// Auto-save job description to localStorage (optional feature)
let saveTimeout;
document.getElementById('jobDescription').addEventListener('input', (e) => {
    clearTimeout(saveTimeout);
    saveTimeout = setTimeout(() => {
        localStorage.setItem('draft_job_description', e.target.value);
    }, 1000);
});

// Restore draft on page load
window.addEventListener('load', () => {
    const draft = localStorage.getItem('draft_job_description');
    if (draft) {
        document.getElementById('jobDescription').value = draft;
    }
});