import http from 'node:http';
import { WebSocketServer } from 'ws';

const port = Number(process.env.LIVEQUIZ_WS_PORT || 6001);
const appUrl = process.env.LIVEQUIZ_APP_URL || process.env.APP_URL || 'http://127.0.0.1:8000';
const clientsByChannel = new Map();

const server = http.createServer(async (request, response) => {
  if (request.method === 'POST' && request.url === '/broadcast') {
    const body = await readJson(request);
    broadcast(body.channel, body.payload);
    response.writeHead(204);
    response.end();
    return;
  }

  response.writeHead(200, { 'Content-Type': 'application/json' });
  response.end(JSON.stringify({ ok: true, name: 'LiveQuiz WebSocket' }));
});

const wss = new WebSocketServer({ server });

wss.on('connection', (socket) => {
  socket.channels = new Set();

  socket.on('message', async (raw) => {
    let message;
    try {
      message = JSON.parse(raw.toString());
    } catch {
      send(socket, { event: 'error', message: 'Некорректное WebSocket-сообщение.' });
      return;
    }

    if (message.type === 'subscribe' && message.sessionId) {
      subscribe(socket, `session:${message.sessionId}`);
      send(socket, { event: 'subscribed', session_id: message.sessionId });
      return;
    }

    if (message.type === 'answer') {
      await proxyAnswer(socket, message);
      return;
    }
  });

  socket.on('close', () => {
    for (const channel of socket.channels) {
      clientsByChannel.get(channel)?.delete(socket);
    }
  });
});

setInterval(() => {
  for (const [channel, clients] of clientsByChannel.entries()) {
    const sessionId = Number(channel.replace('session:', ''));
    for (const socket of clients) {
      send(socket, { event: 'timer.tick', session_id: sessionId, sent_at: new Date().toISOString() });
    }
  }
}, 1000);

server.listen(port, '127.0.0.1', () => {
  console.log(`LiveQuiz WebSocket server: ws://127.0.0.1:${port}`);
});

function subscribe(socket, channel) {
  if (!clientsByChannel.has(channel)) {
    clientsByChannel.set(channel, new Set());
  }

  clientsByChannel.get(channel).add(socket);
  socket.channels.add(channel);
}

async function proxyAnswer(socket, message) {
  if (!message.participantId || !message.token) {
    send(socket, { event: 'answer.error', message: 'Нет данных участника.' });
    return;
  }

  try {
    const response = await fetch(`${appUrl}/api/participants/${message.participantId}/answers?token=${encodeURIComponent(message.token)}`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        answer_id: message.answer_id,
        answer_ids: message.answer_ids,
      }),
    });

    const contentType = response.headers.get('content-type') || '';
    const payload = contentType.includes('application/json') ? await response.json() : await response.text();

    if (!response.ok) {
      send(socket, { event: 'answer.error', message: payload?.message || 'Ответ не принят.' });
      return;
    }

    send(socket, { event: 'answer.accepted', payload });
  } catch (error) {
    send(socket, { event: 'answer.error', message: error.message });
  }
}

function broadcast(channel, payload) {
  const clients = clientsByChannel.get(channel);
  if (!clients) return;

  for (const socket of clients) {
    send(socket, payload);
  }
}

function send(socket, payload) {
  if (socket.readyState === socket.OPEN) {
    socket.send(JSON.stringify(payload));
  }
}

function readJson(request) {
  return new Promise((resolve) => {
    let body = '';
    request.on('data', (chunk) => {
      body += chunk;
    });
    request.on('end', () => {
      try {
        resolve(JSON.parse(body || '{}'));
      } catch {
        resolve({});
      }
    });
  });
}
