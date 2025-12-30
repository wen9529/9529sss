// frontend/src/api.js
import axios from 'axios';

const api = axios.create({
  baseURL: '/backend', // Updated to point to the backend folder
  headers: {
    'Content-Type': 'application/json'
  }
});

api.interceptors.request.use(config => {
  const token = localStorage.getItem('game_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Auth
export const login = (username, password) => 
    api.post('/api/auth', { username, password });

// Lobby
export const getGames = () => 
    api.get('/api/lobby');

export const createGame = (name) => 
    api.post('/api/lobby', { name });

// Game
export const getGameState = (gameId) => 
    api.get(`/api/game/${gameId}`);

export const makeMove = (gameId, move) => 
    api.post(`/api/game/${gameId}`, { move });


export default api;
