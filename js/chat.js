// chat.js - Dedicated chat page logic

function showNotification(message, type = 'success') {
  const n = document.getElementById('notification');
  n.textContent = message;
  n.className = `notification ${type}`;
  n.style.display = 'block';
  setTimeout(() => (n.style.display = 'none'), 4000);
}

function initChatPage() {
  const userId = localStorage.getItem('user_id');
  const userEmail = localStorage.getItem('user_email');
  const emailEl = document.getElementById('userEmail');
  if (emailEl) emailEl.textContent = userEmail || '';

  // Load JD from localStorage and display clearly
  const jd = localStorage.getItem('jd_for_chat') || '';
  const jdPreview = document.getElementById('jdPreview');
  if (jdPreview) jdPreview.textContent = jd ? jd : 'No job description provided.';

  // Allow editing JD inline; save back to storage
  const editBtn = document.getElementById('editJDBtn');
  if (editBtn) {
    editBtn.addEventListener('click', () => {
      const newJD = prompt('Edit Job Description:', jdPreview.textContent || '');
      if (newJD !== null) {
        localStorage.setItem('jd_for_chat', newJD);
        jdPreview.textContent = newJD;
        showNotification('Job description updated for chat context', 'success');
      }
    });
  }

  // Bind send
  const input = document.getElementById('chatInput');
  const sendBtn = document.getElementById('chatSendBtn');
  const box = document.getElementById('chatMessages');

  function append(role, text) {
    const div = document.createElement('div');
    div.className = 'chat-msg ' + (role === 'user' ? 'user' : 'ai');
    div.textContent = text;
    box.appendChild(div);
    box.scrollTop = box.scrollHeight;
  }

  async function send() {
    const text = input.value.trim();
    if (!text) return;
    append('user', text);
    input.value = '';
    try {
      const response = await fetch('php/chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          user_id: userId,
          job_description: localStorage.getItem('jd_for_chat') || '',
          message: text,
          context: ''
        })
      });
      const result = await response.json();
      if (result.success) {
        append('ai', result.reply);
      } else {
        append('ai', (result.message ? `Error: ${result.message}` : 'Sorry, I could not process that.'));
      }
    } catch (e) {
      append('ai', 'Network error. Please try again.');
    }
  }

  if (sendBtn) sendBtn.addEventListener('click', send);
  if (input) input.addEventListener('keydown', (e) => { if (e.key === 'Enter') send(); });

  // Optional greeting
  append('ai', "Hi! I'm your AI coach. Ask about skills, interview prep, or request a mock interview.");
}

function logout() {
  localStorage.removeItem('user_id');
  localStorage.removeItem('user_email');
  showNotification('Logged out successfully', 'success');
  setTimeout(() => { window.location.href = 'index.html'; }, 1000);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initChatPage);
} else {
  initChatPage();
}
