// frontend/src/pages/Login.jsx
import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { login } from '../api';

const Login = () => {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      const res = await login(username, password);
      if (res.data.user) {
        localStorage.setItem('game_user', JSON.stringify(res.data.user));
        navigate('/lobby');
      } else {
        alert(res.data.error || '登录失败');
      }
    } catch (err) {
      alert('登录失败');
    }
  };

  return (
    // 这里的 h-full w-full 配合 App.jsx 的 h-screen 确保全屏
    <div className="h-full w-full flex items-center justify-center bg-gray-100 p-4 overflow-hidden">
      <div className="bg-white p-8 rounded-xl shadow-lg w-full max-w-sm">
        <h1 className="text-2xl font-bold text-center mb-6 text-gray-800">十三水</h1>
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <input 
            type="text" 
            placeholder="用户名" 
            className="border p-3 rounded outline-blue-500"
            value={username}
            onChange={e => setUsername(e.target.value)}
            required
          />
          <input 
            type="password" 
            placeholder="密码 (注册或登录)" 
            className="border p-3 rounded outline-blue-500"
            value={password}
            onChange={e => setPassword(e.target.value)}
            required
          />
          <button className="bg-blue-600 text-white p-3 rounded font-bold hover:bg-blue-700 transition active:scale-95">
            登录 / 注册
          </button>
        </form>
        <p className="text-xs text-center text-gray-400 mt-4">新用户输入用户名和密码自动注册</p>
      </div>
    </div>
  );
};

export default Login;
