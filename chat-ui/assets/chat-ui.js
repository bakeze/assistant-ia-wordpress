(() => {
  const root = document.getElementById('ace-chat-root');
  if (!root || typeof ACE_CONFIG === 'undefined') {
    return;
  }

  const state = {
    open: false,
    proposalId: null,
    messages: [],
    before: '',
    after: '',
  };

  const escapeHtml = (text) => text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/\"/g, '&quot;')
    .replace(/'/g, '&#039;');

  const wordDiff = (before, after) => {
    const a = before.split(/\s+/);
    const b = after.split(/\s+/);
    const out = [];
    let i = 0;
    let j = 0;

    while (i < a.length || j < b.length) {
      if (a[i] === b[j]) {
        out.push(`<span>${escapeHtml(a[i] || '')}</span>`);
        i += 1;
        j += 1;
      } else if (b[j] && !a.includes(b[j])) {
        out.push(`<ins>${escapeHtml(b[j])}</ins>`);
        j += 1;
      } else if (a[i]) {
        out.push(`<del>${escapeHtml(a[i])}</del>`);
        i += 1;
      } else {
        j += 1;
      }
    }

    return out.join(' ');
  };

  const template = `
    <button class="ace-bubble" aria-label="AI Chat Editor">AI</button>
    <section class="ace-window" hidden>
      <header>
        <h3>AI Chat Editor</h3>
        <button class="ace-close" aria-label="Close">×</button>
      </header>
      <div class="ace-messages"></div>
      <div class="ace-typing" hidden>${ACE_CONFIG.i18n.typing}</div>
      <div class="ace-preview" hidden>
        <h4>Preview diff</h4>
        <div class="ace-diff"></div>
        <div class="ace-actions">
          <button class="button button-primary ace-apply">${ACE_CONFIG.i18n.apply}</button>
          <button class="button ace-cancel">${ACE_CONFIG.i18n.cancel}</button>
        </div>
      </div>
      <form class="ace-form">
        <textarea rows="3" placeholder="${ACE_CONFIG.i18n.placeholder}"></textarea>
        <button type="submit" class="button button-primary">Send</button>
      </form>
    </section>
  `;

  root.innerHTML = template;

  const bubble = root.querySelector('.ace-bubble');
  const win = root.querySelector('.ace-window');
  const closeBtn = root.querySelector('.ace-close');
  const messages = root.querySelector('.ace-messages');
  const form = root.querySelector('.ace-form');
  const textarea = form.querySelector('textarea');
  const typing = root.querySelector('.ace-typing');
  const preview = root.querySelector('.ace-preview');
  const diffNode = root.querySelector('.ace-diff');
  const applyBtn = root.querySelector('.ace-apply');
  const cancelBtn = root.querySelector('.ace-cancel');

  const appendMessage = (role, content, stream = false) => {
    const node = document.createElement('article');
    node.className = `ace-msg ace-${role}`;
    messages.appendChild(node);

    if (!stream) {
      node.innerHTML = escapeHtml(content).replace(/\n/g, '<br>');
      messages.scrollTop = messages.scrollHeight;
      return;
    }

    let i = 0;
    const timer = window.setInterval(() => {
      node.innerHTML = escapeHtml(content.slice(0, i)).replace(/\n/g, '<br>');
      i += 3;
      messages.scrollTop = messages.scrollHeight;
      if (i >= content.length + 3) {
        window.clearInterval(timer);
      }
    }, 18);
  };

  const setWindowOpen = (isOpen) => {
    state.open = Boolean(isOpen);
    win.hidden = !state.open;
  };

  const toggleWindow = () => {
    setWindowOpen(!state.open);
  };

  const request = async (path, method = 'GET', body) => {
    const response = await fetch(`${ACE_CONFIG.restUrl}${path}`, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': ACE_CONFIG.nonce,
      },
      body: body ? JSON.stringify(body) : undefined,
    });

    return response.json();
  };

  const loadHistory = async () => {
    const payload = await request('/history');
    (payload.history || []).forEach((row) => {
      appendMessage('user', row.prompt);
      appendMessage('assistant', row.assistant);
    });
  };

  bubble.addEventListener('click', toggleWindow);
  closeBtn.addEventListener('click', () => setWindowOpen(false));

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && state.open) {
      setWindowOpen(false);
    }
  });

  document.addEventListener('click', (event) => {
    if (!state.open) {
      return;
    }

    if (root.contains(event.target)) {
      return;
    }

    setWindowOpen(false);
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const text = textarea.value.trim();
    if (!text) {
      return;
    }

    appendMessage('user', text);
    textarea.value = '';
    typing.hidden = false;
    preview.hidden = true;

    const payload = await request('/chat', 'POST', {
      message: text,
      context: ACE_CONFIG.context,
    });

    typing.hidden = true;

    if (payload.error) {
      appendMessage('assistant', `Error: ${payload.error}`);
      return;
    }

    state.proposalId = payload.proposal_id;
    state.before = payload.before || '';
    state.after = payload.after || '';

    appendMessage('assistant', payload.assistant || 'Ready.', true);

    diffNode.innerHTML = wordDiff(state.before, state.after);
    preview.hidden = false;
  });

  applyBtn.addEventListener('click', async () => {
    if (!state.proposalId) {
      return;
    }

    const payload = await request(`/apply/${state.proposalId}`, 'POST');
    if (payload.error) {
      appendMessage('assistant', `Apply failed: ${payload.error}`);
      return;
    }

    appendMessage('assistant', payload.message || 'Changes applied.');
    state.proposalId = null;
    preview.hidden = true;
  });

  cancelBtn.addEventListener('click', () => {
    state.proposalId = null;
    preview.hidden = true;
    appendMessage('assistant', 'Update canceled.');
  });

  loadHistory().catch(() => {
    // Silent fail for degraded environments.
  });
})();
