import axios from 'axios';

// baseURL 现在指向当前域名下的 /backend/api
// Cloudflare Worker 将会拦截这些请求并代理到真实后端
const API_URL = '/backend/api';

const api = axios.create({
    baseURL: API_URL,
});

// 令牌注入逻辑保持不变
api.interceptors.request.use(config => {
    const token = localStorage.getItem('game_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// 所有 API 调用都保持原样，非常简洁
// Auth
export const loginOrRegister = (username, password) => api.post('/auth.php', { username, password });

// Lobby
export const getGames = () => api.get('/lobby.php');
export const createGame = (name) => api.post('/lobby.php', { name });

// Game
export const getGame = (id) => api.get(`/game.php?id=${id}`);
export const makeMove = (gameId, move) => api.post(`/game.php?id=${gameId}`, { move });

export default api;
