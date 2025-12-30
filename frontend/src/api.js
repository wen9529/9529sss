import axios from 'axios';

const API_URL = 'https://wenge9529.serv00.net/backend/api';

const api = axios.create({
    baseURL: API_URL,
});

api.interceptors.request.use(config => {
    const token = localStorage.getItem('game_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// Auth
export const loginOrRegister = (username, password) => api.post('/auth.php', { username, password });

// Lobby
export const getGames = () => api.get('/lobby.php');
export const createGame = (name) => api.post('/lobby.php', { name });

// Game
export const getGame = (id) => api.get(`/game.php?id=${id}`);
export const makeMove = (gameId, move) => api.post(`/game.php?id=${gameId}`, { move });

export default api;