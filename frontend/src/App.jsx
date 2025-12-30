// frontend/src/App.jsx
import React from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import Login from './pages/Login';
import Lobby from './pages/Lobby';
import GameTable from './pages/GameTable';

const PrivateRoute = ({ children }) => {
  const token = localStorage.getItem('game_token');
  return token ? children : <Navigate to="/login" />;
};

function App() {
  return (
    // 这里的 h-screen 和 overflow-hidden 是防止滚动的关键
    <div className="h-screen w-screen overflow-hidden bg-gray-100 text-gray-900 flex flex-col">
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route 
          path="/lobby" 
          element={
            <PrivateRoute>
              <Lobby />
            </PrivateRoute>
          } 
        />
        <Route 
          path="/game" 
          element={
            <PrivateRoute>
              <GameTable />
            </PrivateRoute>
          } 
        />
        <Route path="*" element={<Navigate to="/lobby" />} />
      </Routes>
    </div>
  );
}

export default App;