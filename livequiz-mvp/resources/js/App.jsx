import React, { useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Link, Route, Routes, useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { QRCodeSVG } from 'qrcode.react';
import {
  ArrowRight,
  BarChart3,
  CheckCircle2,
  ClipboardList,
  Download,
  History,
  LogOut,
  Play,
  Plus,
  QrCode,
  Shield,
  Trash2,
  Trophy,
  Users,
} from 'lucide-react';
import { api } from './lib/api';
import '../css/app.css';

const emptyQuestion = () => ({
  text: '',
  timer_seconds: 25,
  answers: [
    { text: '', is_correct: true },
    { text: '', is_correct: false },
    { text: '', is_correct: false },
    { text: '', is_correct: false },
  ],
});

const activePlayerKey = 'livequiz_active_player';

function readActivePlayer() {
  try {
    return JSON.parse(window.localStorage.getItem(activePlayerKey)) || null;
  } catch {
    return null;
  }
}

function saveActivePlayer(player) {
  window.localStorage.setItem(activePlayerKey, JSON.stringify(player));
}

function clearActivePlayer() {
  window.localStorage.removeItem(activePlayerKey);
}

function usePolling(callback, delay = 1800) {
  useEffect(() => {
    callback();
    const id = window.setInterval(callback, delay);
    return () => window.clearInterval(id);
  }, [callback, delay]);
}

function useCountdownSeconds(serverSeconds = 0, resetKey = '') {
  const [seconds, setSeconds] = useState(Math.max(0, Number(serverSeconds || 0)));

  useEffect(() => {
    setSeconds(Math.max(0, Number(serverSeconds || 0)));
    const id = window.setInterval(() => setSeconds((value) => Math.max(0, value - 1)), 1000);
    return () => window.clearInterval(id);
  }, [serverSeconds, resetKey]);

  return seconds;
}

function TimerBadge({ label, seconds }) {
  return (
    <div className="timer-badge">
      <span>{label}</span>
      <strong>{seconds}</strong>
    </div>
  );
}

function Shell({ children, user, onLogout, activePlayer }) {
  const location = useLocation();
  const player = activePlayer || readActivePlayer();
  const activeGameUrl = player ? `/play/${player.participantId}?token=${encodeURIComponent(player.token)}` : '';
  const showActiveGame = player && location.pathname !== `/play/${player.participantId}`;

  return (
    <div className="app-shell">
      <header className="topbar">
        <Link to="/" className="brand">
          <span className="brand-mark">LQ</span>
          <span>LiveQuiz</span>
        </Link>
        <nav>
          <Link to="/host">Кабинет ведущего</Link>
          <Link to="/join">Присоединиться</Link>
          {showActiveGame && (
            <Link className="active-game-link" to={activeGameUrl}>
              <Play size={16} /> Вернуться к игре
            </Link>
          )}
          {user ? (
            <>
              <span className="user-pill"><Shield size={15} /> {user.name} · {user.role === 'admin' ? 'админ' : 'ведущий'}</span>
              <button className="nav-button" onClick={onLogout}><LogOut size={16} /> Выйти</button>
            </>
          ) : (
            <Link to="/login">Войти</Link>
          )}
        </nav>
      </header>
      <main>{children}</main>
    </div>
  );
}

function RequireAuth({ user, onAuth, children }) {
  if (!user) {
    return <AuthPage onAuth={onAuth} />;
  }

  return children;
}

function Home({ user, onLogout }) {
  return (
    <Shell user={user} onLogout={onLogout}>
      <section className="home-grid">
        <div className="hero-copy">
          <p className="eyebrow">MVP live-викторины для колледжа</p>
          <h1>LiveQuiz</h1>
          <p>
            Ведущий входит в аккаунт, создает квизы и запускает live-сессии. Участники подключаются
            без регистрации по коду или QR, отвечают на вопросы и сразу видят результат.
          </p>
          <div className="action-row">
            <Link className="btn primary" to="/host">
              <ClipboardList size={18} /> Кабинет ведущего
            </Link>
            <Link className="btn ghost" to="/join">
              <ArrowRight size={18} /> Войти по коду
            </Link>
          </div>
        </div>
        <div className="live-board">
          <div className="board-header">
            <span>Сессия A7K92F</span>
            <span className="status-pill active">active</span>
          </div>
          <h2>Что такое MVP?</h2>
          <div className="answers-preview">
            <span className="answer-card correct">Минимально жизнеспособный продукт</span>
            <span className="answer-card">Готовая корпоративная система</span>
            <span className="answer-card">Только дизайн-макет</span>
            <span className="answer-card">Файл с отчетом</span>
          </div>
          <div className="mini-leaderboard">
            <strong>Рейтинг</strong>
            <span>1. Алина - 145</span>
            <span>2. Марк - 130</span>
            <span>3. Тимур - 100</span>
          </div>
        </div>
      </section>
    </Shell>
  );
}

function AuthPage({ onAuth }) {
  const navigate = useNavigate();
  const [mode, setMode] = useState('login');
  const [form, setForm] = useState({ name: '', email: '', password: '' });
  const [error, setError] = useState('');

  async function submit(event) {
    event.preventDefault();
    setError('');
    try {
      const payload = mode === 'register' ? form : { email: form.email, password: form.password };
      const response = await api.post(`/auth/${mode}`, payload);
      window.localStorage.setItem('livequiz_token', response.token);
      onAuth?.(response.user);
      navigate('/host');
    } catch (event) {
      setError(event.message);
    }
  }

  return (
    <Shell>
      <section className="join-screen">
        <form className="join-form auth-form" onSubmit={submit}>
          <p className="eyebrow">Аккаунт ведущего</p>
          <h1>{mode === 'login' ? 'Вход' : 'Регистрация'}</h1>
          {error && <p className="alert">{error}</p>}
          {mode === 'register' && (
            <label>Имя<input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} /></label>
          )}
          <label>Email<input type="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} /></label>
          <label>Пароль<input type="password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} /></label>
          <button className="btn primary" type="submit">{mode === 'login' ? 'Войти' : 'Создать аккаунт'}</button>
          <button className="btn ghost" type="button" onClick={() => setMode(mode === 'login' ? 'register' : 'login')}>
            {mode === 'login' ? 'Зарегистрироваться' : 'Уже есть аккаунт'}
          </button>
          <p className="muted">Участникам аккаунт не нужен. Они входят только по коду сессии.</p>
        </form>
      </section>
    </Shell>
  );
}

function HostDashboard({ user, onLogout }) {
  const [quizzes, setQuizzes] = useState([]);
  const [historyByQuiz, setHistoryByQuiz] = useState({});
  const [error, setError] = useState('');
  const navigate = useNavigate();

  async function load() {
    try {
      const response = await api.get('/quizzes');
      setQuizzes(response.data);
    } catch (event) {
      setError(event.message);
    }
  }

  useEffect(() => {
    load();
  }, []);

  async function startSession(quizId) {
    const response = await api.post(`/quizzes/${quizId}/sessions`);
    navigate(`/host/sessions/${response.data.code}`);
  }

  async function toggleHistory(quizId) {
    if (historyByQuiz[quizId]) {
      setHistoryByQuiz((value) => ({ ...value, [quizId]: null }));
      return;
    }

    const response = await api.get(`/quizzes/${quizId}/sessions`);
    setHistoryByQuiz((value) => ({ ...value, [quizId]: response.data }));
  }

  async function deleteQuiz(quizId) {
    if (!window.confirm('Удалить квиз и всю историю его игр?')) return;
    await api.delete(`/quizzes/${quizId}`);
    await load();
  }

  return (
    <Shell user={user} onLogout={onLogout}>
      <section className="section-head">
        <div>
          <p className="eyebrow">{user?.role === 'admin' ? 'Администратор' : 'Ведущий'}</p>
          <h1>Кабинет викторин</h1>
        </div>
        <Link className="btn primary" to="/host/quizzes/new">
          <Plus size={18} /> Новый квиз
        </Link>
      </section>
      {error && <p className="alert">{error}</p>}
      <div className="quiz-list">
        {quizzes.map((quiz) => (
          <article className="quiz-card" key={quiz.id}>
            <div>
              <h2>{quiz.title}</h2>
              <p>{quiz.description || 'Без описания'}</p>
              <div className="meta-row">
                <span>{quiz.questions_count} вопросов</span>
                <span>{quiz.sessions_count} игр</span>
                <span>Создатель: {quiz.owner?.name || quiz.host_name}</span>
              </div>
              {historyByQuiz[quiz.id] && <QuizHistory sessions={historyByQuiz[quiz.id]} />}
            </div>
            <div className="card-actions">
              <Link className="btn ghost" to={`/host/quizzes/${quiz.id}/edit`}>Редактировать</Link>
              <button className="btn ghost" onClick={() => toggleHistory(quiz.id)}><History size={18} /> История</button>
              <button className="btn primary" onClick={() => startSession(quiz.id)}><Play size={18} /> Запустить</button>
              {quiz.user_id === user.id && (
                <button className="btn danger" onClick={() => deleteQuiz(quiz.id)}><Trash2 size={18} /> Удалить</button>
              )}
            </div>
          </article>
        ))}
      </div>
    </Shell>
  );
}

function QuizHistory({ sessions }) {
  if (sessions.length === 0) {
    return <div className="history-panel"><p>Истории игр пока нет.</p></div>;
  }

  return (
    <div className="history-panel">
      {sessions.map((session) => (
        <div className="history-item" key={session.id}>
          <div>
            <strong>Игра {session.code}</strong>
            <span>{session.status} · участников: {session.participants_count}</span>
          </div>
          <a className="btn ghost" href={api.csvUrl(session.id)}><Download size={16} /> CSV</a>
          <div className="history-leaders">
            {session.leaderboard.slice(0, 5).map((row) => (
              <span key={row.id}>{row.rank}. {row.name} - {row.score}</span>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}

function QuizBuilder({ user, onLogout }) {
  const { id } = useParams();
  const editing = id && id !== 'new';
  const navigate = useNavigate();
  const [form, setForm] = useState({
    title: 'Новая live-викторина',
    description: 'Интерактивный квиз для аудитории',
    host_name: user?.name || 'Преподаватель',
    questions: [emptyQuestion()],
  });
  const [error, setError] = useState('');

  useEffect(() => {
    if (!editing) return;
    api.get(`/quizzes/${id}`).then((response) => setForm(response.data)).catch((event) => setError(event.message));
  }, [editing, id]);

  function updateQuestion(index, patch) {
    setForm((value) => ({
      ...value,
      questions: value.questions.map((question, questionIndex) => questionIndex === index ? { ...question, ...patch } : question),
    }));
  }

  function updateAnswer(questionIndex, answerIndex, patch) {
    setForm((value) => ({
      ...value,
      questions: value.questions.map((question, index) => {
        if (index !== questionIndex) return question;
        return {
          ...question,
          answers: question.answers.map((answer, currentAnswerIndex) => (
            currentAnswerIndex === answerIndex ? { ...answer, ...patch } : answer
          )),
        };
      }),
    }));
  }

  function setCorrect(questionIndex, answerIndex) {
    setForm((value) => ({
      ...value,
      questions: value.questions.map((question, index) => {
        if (index !== questionIndex) return question;
        return {
          ...question,
          answers: question.answers.map((answer, currentAnswerIndex) => ({
            ...answer,
            is_correct: currentAnswerIndex === answerIndex,
          })),
        };
      }),
    }));
  }

  async function submit(event) {
    event.preventDefault();
    setError('');
    try {
      const payload = {
        ...form,
        questions: form.questions.map((question) => ({
          ...question,
          answers: question.answers.filter((answer) => answer.text.trim()),
        })),
      };
      const response = editing ? await api.put(`/quizzes/${id}`, payload) : await api.post('/quizzes', payload);
      navigate('/host', { state: { quizId: response.data.id } });
    } catch (event) {
      setError(event.message);
    }
  }

  return (
    <Shell user={user} onLogout={onLogout}>
      <form className="builder" onSubmit={submit}>
        <section className="section-head">
          <div>
            <p className="eyebrow">Редактор</p>
            <h1>{editing ? 'Редактирование квиза' : 'Создание квиза'}</h1>
          </div>
          <button className="btn primary" type="submit"><CheckCircle2 size={18} /> Сохранить</button>
        </section>
        {error && <p className="alert">{error}</p>}
        <div className="form-grid">
          <label>Название<input value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} /></label>
          <label>Ведущий<input value={form.host_name || ''} onChange={(event) => setForm({ ...form, host_name: event.target.value })} /></label>
          <label className="wide">Описание<textarea value={form.description || ''} onChange={(event) => setForm({ ...form, description: event.target.value })} /></label>
        </div>
        {form.questions.map((question, questionIndex) => (
          <section className="question-editor" key={questionIndex}>
            <div className="question-title-row">
              <h2>Вопрос {questionIndex + 1}</h2>
              <label>Таймер<input type="number" min="10" max="120" value={question.timer_seconds} onChange={(event) => updateQuestion(questionIndex, { timer_seconds: Number(event.target.value) })} /></label>
            </div>
            <textarea className="question-input" placeholder="Текст вопроса" value={question.text} onChange={(event) => updateQuestion(questionIndex, { text: event.target.value })} />
            <div className="answer-grid">
              {question.answers.map((answer, answerIndex) => (
                <label className={`answer-input ${answer.is_correct ? 'selected' : ''}`} key={answerIndex}>
                  <input type="radio" name={`correct-${questionIndex}`} checked={answer.is_correct} onChange={() => setCorrect(questionIndex, answerIndex)} />
                  <input placeholder={`Вариант ${answerIndex + 1}`} value={answer.text} onChange={(event) => updateAnswer(questionIndex, answerIndex, { text: event.target.value })} />
                </label>
              ))}
            </div>
          </section>
        ))}
        <button className="btn ghost" type="button" onClick={() => setForm({ ...form, questions: [...form.questions, emptyQuestion()] })}>
          <Plus size={18} /> Добавить вопрос
        </button>
      </form>
    </Shell>
  );
}

function SessionRoom({ user, onLogout }) {
  const { code } = useParams();
  const [session, setSession] = useState(null);
  const [stats, setStats] = useState([]);
  const [error, setError] = useState('');

  const load = React.useCallback(async () => {
    try {
      const response = await api.get(`/sessions/${code}`);
      setSession(response.data);
      if (response.data.current_question) {
        const statResponse = await api.get(`/sessions/${response.data.id}/answer-stats`);
        setStats(statResponse.data);
      }
    } catch (event) {
      setError(event.message);
    }
  }, [code]);

  usePolling(load, 1600);

  async function action(path) {
    const response = await api.post(`/sessions/${session.id}/${path}`);
    setSession(response.data);
  }

  const phaseKey = `${session?.current_question?.id || 'none'}-${session?.current_phase || 'idle'}`;
  const questionSeconds = useCountdownSeconds(session?.question_seconds_remaining, `${phaseKey}-question`);
  const revealSeconds = useCountdownSeconds(session?.reveal_seconds_remaining, `${phaseKey}-reveal`);

  if (!session) {
    return <Shell user={user} onLogout={onLogout}><p className="loading">Загрузка сессии...</p>{error && <p className="alert">{error}</p>}</Shell>;
  }

  return (
    <Shell user={user} onLogout={onLogout}>
      <section className="session-layout">
        <aside className="session-aside">
          <div className="join-card">
            <QrCode size={20} />
            <h2>{session.code}</h2>
            <QRCodeSVG value={session.join_url} size={150} />
            <a href={session.join_url}>{session.join_url}</a>
          </div>
          <div className="participant-panel">
            <h3><Users size={18} /> Участники</h3>
            {session.participants.map((participant) => (
              <span className="participant-chip" key={participant.id}>
                <i style={{ background: participant.avatar_color }} /> {participant.name}
              </span>
            ))}
          </div>
        </aside>
        <section className="session-main">
          <div className="section-head compact">
            <div>
              <p className="eyebrow">Сессия ведущего</p>
              <h1>{session.quiz.title}</h1>
            </div>
            <span className={`status-pill ${session.status}`}>{session.current_phase || session.status}</span>
          </div>
          <div className="host-controls">
            {session.status === 'waiting' && <button className="btn primary" onClick={() => action('start')}><Play size={18} /> Начать</button>}
            {session.status === 'active' && <button className="btn primary" onClick={() => action('advance')}><ArrowRight size={18} /> Следующий вопрос</button>}
            {session.status !== 'finished' && <button className="btn danger" onClick={() => action('finish')}>Завершить</button>}
            <a className="btn ghost" href={api.csvUrl(session.id)}><Download size={18} /> CSV</a>
          </div>
          {session.current_question && (
            <article className="question-stage">
              <div className="stage-row">
                <p className="eyebrow">Вопрос {session.current_question.position} из {session.quiz.questions.length}</p>
                {session.current_phase === 'reveal'
                  ? <TimerBadge label="Следующий через" seconds={revealSeconds} />
                  : <TimerBadge label="Осталось" seconds={questionSeconds} />}
              </div>
              <h2>{session.current_question.text}</h2>
              {session.current_phase === 'reveal' && <div className="reveal-note">Показываем правильный ответ. После таймера игра перейдет дальше.</div>}
              <div className="answers-preview">
                {session.current_question.answers.map((answer) => (
                  <span className={`answer-card ${answer.is_correct ? 'correct' : ''}`} key={answer.id}>{answer.text}</span>
                ))}
              </div>
            </article>
          )}
          <div className="analytics-grid">
            <Leaderboard rows={session.leaderboard} />
            <AnswerChart stats={stats} />
          </div>
        </section>
      </section>
    </Shell>
  );
}

function JoinPage({ user, onLogout }) {
  const [searchParams] = useSearchParams();
  const [code, setCode] = useState(searchParams.get('code') || '');
  const [name, setName] = useState('');
  const [error, setError] = useState('');
  const navigate = useNavigate();

  async function join(event) {
    event.preventDefault();
    setError('');
    try {
      const response = await api.post(`/sessions/${code}/join`, { name });
      window.localStorage.setItem(`participant:${response.data.id}`, response.token);
      saveActivePlayer({ participantId: response.data.id, token: response.token });
      navigate(`/play/${response.data.id}?token=${response.token}`);
    } catch (event) {
      setError(event.message);
    }
  }

  return (
    <Shell user={user} onLogout={onLogout}>
      <section className="join-screen">
        <form className="join-form" onSubmit={join}>
          <p className="eyebrow">Вход участника</p>
          <h1>Присоединиться к игре</h1>
          {error && <p className="alert">{error}</p>}
          <label>Код сессии<input value={code} onChange={(event) => setCode(event.target.value.toUpperCase())} /></label>
          <label>Имя или никнейм<input value={name} onChange={(event) => setName(event.target.value)} /></label>
          <button className="btn primary" type="submit"><ArrowRight size={18} /> Войти</button>
        </form>
      </section>
    </Shell>
  );
}

function ParticipantPlay({ user, onLogout }) {
  const { participantId } = useParams();
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token') || window.localStorage.getItem(`participant:${participantId}`);
  const [state, setState] = useState(null);
  const [selected, setSelected] = useState(null);
  const [error, setError] = useState('');

  const load = React.useCallback(async () => {
    if (!token) return;
    try {
      const response = await api.get(`/participants/${participantId}/status?token=${token}`);
      setState(response.data);
    } catch (event) {
      setError(event.message);
    }
  }, [participantId, token]);

  usePolling(load, 1000);

  const phaseKey = `${state?.session?.current_question?.id || 'none'}-${state?.session?.current_phase || 'idle'}`;
  const questionSeconds = useCountdownSeconds(state?.session?.question_seconds_remaining, `${phaseKey}-question`);
  const revealSeconds = useCountdownSeconds(state?.session?.reveal_seconds_remaining, `${phaseKey}-reveal`);

  useEffect(() => {
    if (token) {
      saveActivePlayer({ participantId, token });
    }
  }, [participantId, token]);

  useEffect(() => {
    setSelected(null);
  }, [state?.session?.current_question?.id]);

  async function answer(answerId) {
    setSelected(answerId);
    setError('');
    try {
      await api.post(`/participants/${participantId}/answers?token=${token}`, { answer_id: answerId });
      await load();
    } catch (event) {
      setError(event.message);
    }
  }

  if (!state) return <Shell user={user} onLogout={onLogout}><p className="loading">Подключение к комнате...</p>{error && <p className="alert">{error}</p>}</Shell>;

  const { session, participant } = state;
  if (session.status === 'finished') {
    return <ResultPage embedded participantId={participantId} token={token} />;
  }

  return (
    <Shell user={user} onLogout={onLogout}>
      <section className="player-screen">
        <div className="player-top">
          <span>{participant.name}</span>
          <strong>{participant.score} баллов</strong>
        </div>
        {session.status === 'waiting' && (
          <div className="waiting">
            <Users size={42} />
            <h1>Ожидаем старт</h1>
            <p>Ведущий скоро запустит первый вопрос.</p>
          </div>
        )}
        {session.status === 'active' && session.current_question && (
          <article className="player-question">
            <div className="stage-row centered">
              <p className="eyebrow">Вопрос {session.current_question.position} из {session.quiz.question_count}</p>
              {session.current_phase === 'reveal'
                ? <TimerBadge label="Дальше через" seconds={revealSeconds} />
                : <TimerBadge label="Осталось" seconds={questionSeconds} />}
            </div>
            <h1>{session.current_question.text}</h1>
            {session.current_phase === 'reveal' ? (
              <>
                <ResultNotice result={session.current_answer_result} />
                {!session.current_answer_result?.is_correct && (
                  <div className="player-answers reveal">
                    {session.current_question.answers.map((item) => (
                      <button
                        className={[
                          'answer-button',
                          item.is_correct ? 'correct' : '',
                          session.current_answer_result?.selected_answer_id === item.id && !item.is_correct ? 'wrong-selected' : '',
                        ].filter(Boolean).join(' ')}
                        key={item.id}
                        disabled
                      >
                        {item.text}
                      </button>
                    ))}
                  </div>
                )}
              </>
            ) : session.answered_current_question ? (
              <div className="accepted"><CheckCircle2 size={38} /> Ответ принят</div>
            ) : (
              <div className="player-answers">
                {session.current_question.answers.map((item) => (
                  <button className={selected === item.id ? 'answer-button selected' : 'answer-button'} key={item.id} onClick={() => answer(item.id)}>
                    {item.text}
                  </button>
                ))}
              </div>
            )}
          </article>
        )}
        {error && <p className="alert">{error}</p>}
      </section>
    </Shell>
  );
}

function ResultNotice({ result }) {
  if (!result?.answered) return <div className="answer-result wrong">Время вышло. Ответ не верный.</div>;
  if (result.is_correct) return <div className="answer-result correct">Ответ верный! +{result.score} баллов</div>;
  return <div className="answer-result wrong">Ответ не верный</div>;
}

function ResultPage({ embedded = false, participantId: propParticipantId, token: propToken }) {
  const params = useParams();
  const [searchParams] = useSearchParams();
  const participantId = propParticipantId || params.participantId;
  const token = propToken || searchParams.get('token') || window.localStorage.getItem(`participant:${participantId}`);
  const [result, setResult] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    api.get(`/participants/${participantId}/result?token=${token}`)
      .then((response) => {
        setResult(response.data);
        if (response.data.session_status === 'finished') {
          clearActivePlayer();
        }
      })
      .catch((event) => setError(event.message));
  }, [participantId, token]);

  const content = (
    <section className="result-screen">
      {error && <p className="alert">{error}</p>}
      {result && (
        <article className="result-card">
          <Trophy size={52} />
          <p className="eyebrow">Личный результат</p>
          <h1>{result.participant.name}</h1>
          <div className="score-line">{result.score} баллов</div>
          <div className="result-grid">
            <span>Место <strong>{result.rank} из {result.total_participants}</strong></span>
            <span>Правильных <strong>{result.correct_answers}</strong></span>
            <span>Успешность <strong>{result.accuracy}%</strong></span>
          </div>
          {result.mistakes.length > 0 && (
            <div className="mistakes">
              <h2>Ошибки</h2>
              {result.mistakes.map((mistake, index) => (
                <p key={index}><strong>{mistake.question}</strong><br />Выбран ответ: {mistake.selected_answer}</p>
              ))}
            </div>
          )}
          <div className="final-leaderboard">
            <h2>Общий рейтинг</h2>
            {result.leaderboard.map((row) => (
              <div className={`final-leader-row ${row.id === result.participant.id ? 'self' : ''}`} key={row.id}>
                <span className="rank">{row.rank}</span>
                <i style={{ background: row.avatar_color }} />
                <strong>{row.name}</strong>
                <span>{row.score} баллов</span>
                <small>{row.correct_answers} правильных · {row.accuracy}%</small>
              </div>
            ))}
          </div>
          <div className="result-actions">
            <Link className="btn primary" to="/"><ArrowRight size={18} /> Вернуться на главную</Link>
          </div>
        </article>
      )}
    </section>
  );

  return embedded ? content : <Shell>{content}</Shell>;
}

function Leaderboard({ rows }) {
  return (
    <article className="panel">
      <h2><Trophy size={18} /> Рейтинг</h2>
      {rows.length === 0 && <p>Участники появятся после подключения.</p>}
      {rows.map((row) => (
        <div className="leader-row" key={row.id}>
          <span className="rank">{row.rank}</span>
          <i style={{ background: row.avatar_color }} />
          <strong>{row.name}</strong>
          <span>{row.score}</span>
          <small>{row.badges?.join(', ')}</small>
        </div>
      ))}
    </article>
  );
}

function AnswerChart({ stats }) {
  const data = useMemo(() => stats.map((item) => ({ name: item.text.slice(0, 18), count: item.count })), [stats]);

  return (
    <article className="panel">
      <h2><BarChart3 size={18} /> Ответы</h2>
      {data.length === 0 ? <p>Статистика появится после старта вопроса.</p> : (
        <ResponsiveContainer width="100%" height={240}>
          <BarChart data={data}>
            <CartesianGrid strokeDasharray="3 3" />
            <XAxis dataKey="name" />
            <YAxis allowDecimals={false} />
            <Tooltip />
            <Bar dataKey="count" fill="#2563eb" radius={[6, 6, 0, 0]} />
          </BarChart>
        </ResponsiveContainer>
      )}
    </article>
  );
}

function App() {
  const [user, setUser] = useState(null);
  const [checkingAuth, setCheckingAuth] = useState(true);

  useEffect(() => {
    const token = window.localStorage.getItem('livequiz_token');
    if (!token) {
      setCheckingAuth(false);
      return;
    }

    api.get('/auth/me')
      .then((response) => setUser(response.user))
      .catch(() => window.localStorage.removeItem('livequiz_token'))
      .finally(() => setCheckingAuth(false));
  }, []);

  async function logout() {
    try {
      await api.post('/auth/logout');
    } catch {
      // Local logout should still happen if token is already invalid.
    }
    window.localStorage.removeItem('livequiz_token');
    setUser(null);
  }

  return (
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<Home user={user} onLogout={logout} />} />
        <Route path="/login" element={<AuthPage onAuth={setUser} />} />
        <Route path="/host" element={checkingAuth ? <Shell><p className="loading">Загрузка...</p></Shell> : <RequireAuth user={user} onAuth={setUser}><HostDashboard user={user} onLogout={logout} /></RequireAuth>} />
        <Route path="/host/quizzes/:id/edit" element={checkingAuth ? <Shell><p className="loading">Загрузка...</p></Shell> : <RequireAuth user={user} onAuth={setUser}><QuizBuilder user={user} onLogout={logout} /></RequireAuth>} />
        <Route path="/host/quizzes/new" element={checkingAuth ? <Shell><p className="loading">Загрузка...</p></Shell> : <RequireAuth user={user} onAuth={setUser}><QuizBuilder user={user} onLogout={logout} /></RequireAuth>} />
        <Route path="/host/sessions/:code" element={checkingAuth ? <Shell><p className="loading">Загрузка...</p></Shell> : <RequireAuth user={user} onAuth={setUser}><SessionRoom user={user} onLogout={logout} /></RequireAuth>} />
        <Route path="/join" element={<JoinPage user={user} onLogout={logout} />} />
        <Route path="/play/:participantId" element={<ParticipantPlay user={user} onLogout={logout} />} />
        <Route path="/result/:participantId" element={<ResultPage />} />
      </Routes>
    </BrowserRouter>
  );
}

createRoot(document.getElementById('root')).render(<App />);
