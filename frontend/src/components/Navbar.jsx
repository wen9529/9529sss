// frontend/src/components/Navbar.jsx
import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { searchUser, transferPoints, getUserInfo } from '../api';

const Navbar = () => {
  const navigate = useNavigate();
  const [user, setUser] = useState(null);
  const [showModal, setShowModal] = useState(false);
  const [searchPhone, setSearchPhone] = useState('');
  const [searchResult, setSearchResult] = useState(null);
  const [amount, setAmount] = useState('');
  const [msg, setMsg] = useState('');

  // 初始化时从 API 获取最新数据
  const fetchLatestUser = async () => {
    try {
      const res = await getUserInfo();
      if (res.data.status === 'success') {
        setUser(res.data.user);
        localStorage.setItem('game_user', JSON.stringify(res.data.user));
      }
    } catch (e) {
      const local = localStorage.getItem('game_user');
      if (local) setUser(JSON.parse(local));
    }
  };

  useEffect(() => {
    fetchLatestUser();
    const timer = setInterval(fetchLatestUser, 10000);
    return () => clearInterval(timer);
  }, []);

  const handleLogout = () => {
    localStorage.removeItem('game_token');
    localStorage.removeItem('game_user');
    navigate('/login');
  };

  const handleSearch = async () => {
    try {
      const res = await searchUser(searchPhone);
      if (res.data.status === 'success') {
        setSearchResult(res.data);
        setMsg('');
      } else {
        setSearchResult(null);
        setMsg('用户未找到');
      }
    } catch (e) {
      setMsg('搜索出错');
    }
  };

  const handleTransfer = async () => {
    if (!searchResult || !amount) return;
    try {
      const res = await transferPoints(searchResult.game_id, amount);
      if (res.data.status === 'success') {
        alert('转账成功！');
        setAmount('');
        setShowModal(false);
        fetchLatestUser(); 
      } else {
        setMsg(res.data.message);
      }
    } catch (e) {
      setMsg('转账失败');
    }
  };

  return (
    <nav className="bg-blue-600 text-white p-4 flex justify-between items-center shadow-md shrink-0">
      <div className="font-bold text-lg">十三水 ({user?.game_id || '...'})</div>
      <div className="flex gap-4 items-center">
        <span className="text-yellow-300 font-mono font-bold text-lg">💰 {user?.points ?? '...'}</span>
        <button onClick={() => setShowModal(true)} className="bg-blue-500 px-3 py-1 rounded hover:bg-blue-400 text-sm border border-blue-400">
          积分管理
        </button>
        <button onClick={handleLogout} className="bg-red-500 px-3 py-1 rounded hover:bg-red-400 text-sm border border-red-400">
          退出
        </button>
      </div>

      {/* 积分弹窗 */}
      {showModal && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 px-4">
          <div className="bg-white text-gray-800 p-6 rounded-lg w-full max-w-sm shadow-2xl">
            <h3 className="text-xl font-bold mb-4 text-center">积分转账</h3>
            
            <div className="flex gap-2 mb-4">
              <input 
                type="text" 
                placeholder="输入对方手机号" 
                className="border border-gray-300 p-2 flex-1 rounded focus:outline-blue-500"
                value={searchPhone}
                onChange={(e) => setSearchPhone(e.target.value)}
              />
              <button onClick={handleSearch} className="bg-blue-600 text-white px-4 rounded hover:bg-blue-700">搜索</button>
            </div>

            {msg && <p className="text-red-500 mb-2 text-sm text-center">{msg}</p>}

            {searchResult && (
              <div className="bg-green-50 p-3 rounded mb-4 border border-green-200">
                <p className="text-sm text-gray-600">目标ID: <span className="font-bold text-green-700 text-lg">{searchResult.game_id}</span></p>
                <input 
                  type="number" 
                  placeholder="输入转账金额" 
                  className="border border-gray-300 p-2 w-full mt-2 rounded focus:outline-green-500"
                  value={amount}
                  onChange={(e) => setAmount(e.target.value)}
                />
                <button 
                  onClick={handleTransfer}
                  className="bg-green-600 text-white w-full mt-3 py-2 rounded font-bold hover:bg-green-700 transition"
                >
                  确认转账
                </button>
              </div>
            )}

            <button onClick={() => setShowModal(false)} className="w-full mt-2 text-gray-500 py-2 hover:bg-gray-100 rounded">取消</button>
          </div>
        </div>
      )}
    </nav>
  );
};

export default Navbar;