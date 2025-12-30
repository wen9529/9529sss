// frontend/src/components/Navbar.jsx
import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';

const Navbar = () => {
  const navigate = useNavigate();
  const [user, setUser] = useState(null);

  useEffect(() => {
    // Try to get user from local storage
    const localUser = localStorage.getItem('game_user');
    if (localUser) {
      setUser(JSON.parse(localUser));
    }
  }, []);

  const handleLogout = () => {
    localStorage.removeItem('game_token');
    localStorage.removeItem('game_user');
    navigate('/login');
  };

  return (
    <nav className="bg-white shadow-md p-4 flex justify-between items-center">
      <div className="text-xl font-bold text-gray-800">十三水</div>
      <div className="flex items-center gap-4">
        {user && <span className="text-gray-600">欢迎, {user.username}</span>}
        <button
          onClick={handleLogout}
          className="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition"
        >
          注销
        </button>
      </div>
    </nav>
  );
};

export default Navbar;
