import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const $ = (s) => document.querySelector(s);
const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const state = {
    session: JSON.parse(localStorage.getItem('chat.session') || 'null'),
    friends: [],
    groups: [],
    direct: {}, // peer_id -> conversation id
    active: null, // { type: 'user'|'group', id, name }
    messages: new Map(), // message_id -> message (all seen)
    pending: new Map(), // client_msg_id -> element
    curMax: BigInt(localStorage.getItem('chat.curMax') || '0'),
    nextBefore: null,
    heartbeat: null,
    echo: null,
};

const api = axios.create({ baseURL: '/api/v1' });
api.interceptors.request.use((c) => {
    if (state.session) c.headers.Authorization = `Bearer ${state.session.token}`;
    return c;
});

// ---------- auth ----------
async function auth(kind) {
    const email = $('#email').value, password = $('#password').value, name = $('#name').value;
    $('#auth-error').textContent = '';
    try {
        if (kind === 'register') await api.post('/auth/register', { name, email, password });
        const { data } = await api.post('/auth/login', { email, password });
        state.session = data;
        localStorage.setItem('chat.session', JSON.stringify(data));
        localStorage.setItem('chat.curMax', '0');
        state.curMax = 0n;
        start();
    } catch (e) {
        $('#auth-error').textContent = e.response?.data?.error?.message || e.response?.data?.message || 'Request failed';
    }
}

async function logout() {
    try { await api.post('/auth/logout'); } catch (_) {}
    stop();
    state.session = null;
    localStorage.removeItem('chat.session');
    render();
}

// ---------- realtime ----------
function connect() {
    const { chat_server: srv } = state.session;
    state.echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: srv.host,
        wsPort: srv.port,
        wssPort: srv.port,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/api/broadcasting/auth',
        auth: { headers: { Authorization: `Bearer ${state.session.token}` } },
    });

    const ch = state.echo.private(`user.${state.session.user_id}`);
    ch.listen('.message.new', onNewMessage)
        .listen('.message.status', onStatus)
        .listen('.presence.changed', onPresence);

    // Reconnect: catch up from this device's cur_max_message_id so nothing sent while offline is lost.
    const conn = state.echo.connector.pusher.connection;
    conn.bind('connected', () => { setConn(true); sync(); });
    conn.bind('disconnected', () => setConn(false));
    conn.bind('unavailable', () => setConn(false));

    beat();
    state.heartbeat = setInterval(beat, 5000);
}

function stop() {
    clearInterval(state.heartbeat);
    state.echo?.disconnect();
    state.echo = null;
}

const beat = () => api.post('/presence/heartbeat').catch(() => {});
const setConn = (ok) => { $('#conn').textContent = ok ? 'live' : 'reconnecting...'; $('#conn').className = ok ? 'ok' : 'bad'; };

// ---------- data ----------
async function loadSidebar() {
    const [f, c] = await Promise.all([api.get('/friends'), api.get('/conversations')]);
    state.friends = f.data.friends;
    state.groups = c.data.groups;
    state.direct = Object.fromEntries(c.data.direct.map((d) => [d.peer_id, d.id]));
    renderSidebar();
}

async function sync() {
    let more = true;
    while (more) {
        const { data } = await api.get('/sync', { params: { after: state.curMax.toString() } });
        data.messages.forEach((m) => onNewMessage(m, false));
        advance(data.cur_max_message_id);
        more = data.has_more;
    }
    loadSidebar();
}

function advance(id) {
    const v = BigInt(id);
    if (v > state.curMax) {
        state.curMax = v;
        localStorage.setItem('chat.curMax', v.toString());
    }
}

// ---------- messages ----------
function inActive(m) {
    const a = state.active;
    if (!a) return false;
    return a.type === 'group' ? m.is_group && m.group_id === a.id : !m.is_group && (m.from === a.id || m.to === a.id);
}

function bubble(m, mine) {
    const el = document.createElement('div');
    el.className = 'msg ' + (mine ? 'mine' : 'theirs');
    el.dataset.id = m.message_id;
    const who = m.is_group && !mine ? `<b>${esc(nameOf(m.from))}</b><br>` : '';
    el.innerHTML = `${who}${esc(m.content)}<small>${new Date(m.created_at).toLocaleTimeString()}<span class="tick"></span></small>`;
    return el;
}

const nameOf = (id) => state.friends.find((f) => f.id === id)?.name || `user ${id}`;

function onNewMessage(m, live = true) {
    // Exactly-once display: dedupe by message id, and by client_msg_id for our own optimistic bubble.
    if (state.messages.has(m.message_id)) return;
    state.messages.set(m.message_id, m);
    advance(m.message_id);

    const mine = m.from === state.session.user_id;
    const pend = m.client_msg_id && state.pending.get(m.client_msg_id);
    if (pend) {
        pend.dataset.id = m.message_id;
        state.pending.delete(m.client_msg_id);
    } else if (inActive(m)) {
        const list = $('#messages');
        list.append(bubble(m, mine));
        list.scrollTop = list.scrollHeight;
    } else if (live && !mine) {
        const key = m.is_group ? `g${m.group_id}` : `u${m.from}`;
        document.querySelector(`[data-key="${key}"]`)?.classList.add('unread');
    }
    if (live && !mine) api.post(`/messages/${m.message_id}/delivered`).catch(() => {});
}

function onStatus(e) {
    const el = document.querySelector(`.msg[data-id="${e.message_id}"] .tick`);
    if (el) el.textContent = ' ✓✓';
}

function onPresence(e) {
    const f = state.friends.find((x) => x.id === e.user_id);
    if (f) { f.status = e.status; renderSidebar(); }
}

async function open(type, id, name) {
    state.active = { type, id, name };
    state.nextBefore = null;
    $('#messages').innerHTML = '';
    $('#title').textContent = name;
    $('#composer').hidden = false;
    document.querySelectorAll('.unread').forEach((e) => e.classList.remove('unread'));
    await loadHistory(true);
}

async function loadHistory(initial) {
    const a = state.active;
    let url;
    if (a.type === 'group') url = `/groups/${a.id}/messages`;
    else if (state.direct[a.id]) url = `/conversations/${state.direct[a.id]}/messages`;
    else { $('#more').hidden = true; return; } // no conversation yet
    const { data } = await api.get(url, { params: { before: state.nextBefore ?? undefined, limit: 30 } });
    const list = $('#messages');
    const prevHeight = list.scrollHeight;
    const frag = document.createDocumentFragment();
    [...data.messages].reverse().forEach((m) => {
        state.messages.set(m.message_id, m);
        frag.append(bubble(m, m.from === state.session.user_id));
    });
    list.prepend(frag);
    list.scrollTop = initial ? list.scrollHeight : list.scrollHeight - prevHeight;
    state.nextBefore = data.next_before;
    $('#more').hidden = !data.next_before;
}

async function send(e) {
    e.preventDefault();
    const input = $('#text');
    const content = input.value.trim();
    if (!content || !state.active) return;
    input.value = '';

    const a = state.active;
    const client_msg_id = 'c-' + crypto.randomUUID();
    const body = a.type === 'group' ? { group_id: a.id } : { to_user_id: a.id };
    const el = bubble({ message_id: client_msg_id, content, created_at: new Date().toISOString(), from: state.session.user_id }, true);
    el.classList.add('sending');
    state.pending.set(client_msg_id, el);
    $('#messages').append(el);
    $('#messages').scrollTop = $('#messages').scrollHeight;

    // Retry with the SAME client_msg_id until acked: the server dedupes, so retries never duplicate.
    for (let attempt = 0; attempt < 6; attempt++) {
        try {
            const { data } = await api.post('/messages', { client_msg_id, content, ...body });
            el.classList.remove('sending');
            el.dataset.id = data.message_id;
            el.querySelector('.tick').textContent = ' ✓';
            if (!state.messages.has(data.message_id)) state.messages.set(data.message_id, { message_id: data.message_id, content });
            state.pending.delete(client_msg_id);
            if (a.type === 'user' && !state.direct[a.id]) loadSidebar();
            return;
        } catch (err) {
            if (err.response && err.response.status < 500 && err.response.status !== 429) {
                el.classList.add('failed');
                el.querySelector('small').textContent = err.response.data?.error?.message || 'Not sent';
                return;
            }
            await new Promise((r) => setTimeout(r, Math.min(1000 * 2 ** attempt, 30000) * (0.5 + Math.random())));
        }
    }
    el.classList.add('failed');
}

// ---------- ui ----------
function renderSidebar() {
    const key = (t, id) => `${t === 'group' ? 'g' : 'u'}${id}`;
    $('#friends').innerHTML = state.friends.map((f) =>
        `<li data-key="${key('user', f.id)}" data-type="user" data-id="${f.id}" data-name="${esc(f.name)}"><i class="dot ${f.status}"></i>${esc(f.name)}</li>`).join('') || '<li class="empty">No friends yet</li>';
    $('#groups').innerHTML = state.groups.map((g) =>
        `<li data-key="${key('group', g.id)}" data-type="group" data-id="${g.id}" data-name="${esc(g.name)}"># ${esc(g.name)} <small>${g.member_ids.length}</small></li>`).join('') || '<li class="empty">No groups yet</li>';
    $('#members').innerHTML = state.friends.map((f) => `<label><input type="checkbox" value="${f.id}"> ${esc(f.name)}</label>`).join('');
}

function render() {
    const on = !!state.session;
    $('#auth').hidden = on;
    $('#app').hidden = !on;
    if (on) $('#me').textContent = state.session.name;
}

function start() {
    render();
    connect();
    loadSidebar().then(sync);
}

document.addEventListener('DOMContentLoaded', () => {
    $('#login').onclick = () => auth('login');
    $('#register').onclick = () => auth('register');
    $('#logout').onclick = logout;
    $('#composer').onsubmit = send;
    $('#more').onclick = () => loadHistory(false);
    document.addEventListener('click', (e) => {
        const li = e.target.closest('#friends li[data-id], #groups li[data-id]');
        if (li) open(li.dataset.type, Number(li.dataset.id), li.dataset.name);
    });
    $('#add-friend').onsubmit = async (e) => {
        e.preventDefault();
        try { await api.post('/friends', { email: $('#friend-email').value }); $('#friend-email').value = ''; await loadSidebar(); }
        catch (err) { alert(err.response?.data?.error?.message || 'Could not add friend'); }
    };
    $('#new-group').onsubmit = async (e) => {
        e.preventDefault();
        const member_ids = [...document.querySelectorAll('#members input:checked')].map((i) => Number(i.value));
        try { await api.post('/groups', { name: $('#group-name').value, member_ids }); $('#group-name').value = ''; await loadSidebar(); }
        catch (err) { alert(err.response?.data?.error?.message || err.response?.data?.message || 'Could not create group'); }
    };
    // A 401 means the token was revoked: drop the session.
    api.interceptors.response.use((r) => r, (err) => { if (err.response?.status === 401 && state.session && !err.config.url.includes('/auth/')) logout(); return Promise.reject(err); });
    state.session ? start() : render();
});
