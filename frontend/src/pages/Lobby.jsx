// frontend/src/pages/Lobby.jsx
import React, { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import Navbar from '../components/Navbar';
import { joinGame } from '../api';

const Lobby = () => {
  const navigate = useNavigate();

  // 每次进入大厅，检查是否有未完成的牌局
  useEffect(() => {
    checkExistingGame();
  }, []);

  const checkExistingGame = async () => {
    try {
      // 尝试请求加入接口（带重连检测逻辑）
      // 这里的 level 参数不重要，如果是重连，后端会忽略 level 直接返回旧 session
      const res = await joinGame(2); 
      if (res.data.status === 'success' && res.data.message?.includes('重连')) {
         // 发现旧牌局，直接跳转
         navigate('/game');
      }
    } catch (e) {
      // 忽略错误，说明没有进行中的牌局
    }
  };

  const handleJoin = async (level) => {
    try {
      const res = await joinGame(level);
      if (res.data.status === 'success') {
        navigate('/game');
      } else {
        alert(res.data.message || '加入失败');
      }
    } catch (e) {
      console.error(e);
      alert('网络错误');
    }
  };

  return (
    <div className="h-full flex flex-col overflow-hidden">
      <Navbar />
      
      <div className="flex-1 bg-gray-100 p-4 flex flex-col gap-4 justify-center items-center overflow-hidden">
        <div className="w-full max-w-md flex flex-col gap-4">
          {/* 2分场 */}
          <div 
            onClick={() => handleJoin(2)}
            className="bg-gradient-to-r from-green-500 to-green-700 text-white h-32 rounded-2xl shadow-xl flex items-center justify-center text-3xl font-bold cursor-pointer active:scale-95 transition-transform border-2 border-green-400/30"
          >
            2 分场
          </div>

          {/* 5分场 */}
          <div 
            onClick={() => handleJoin(5)}
            className="bg-gradient-to-r from-blue-500 to-blue-700 text-white h-32 rounded-2xl shadow-xl flex items-center justify-center text-3xl font-bold cursor-pointer active:scale-95 transition-transform border-2 border-blue-400/30"
          >
            5 分场
          </div>

          {/* 10分场 */}
          <div 
            onClick={() => handleJoin(10)}
            className="bg-gradient-to-r from-purple-500 to-purple-700 text-white h-32 rounded-2xl shadow-xl flex items-center justify-center text-3xl font-bold cursor-pointer active:scale-95 transition-transform border-2 border-purple-400/30"
          >
            10 分场
          </div>
        </div>
      </div>
    </div>
  );
};

export default Lobby;