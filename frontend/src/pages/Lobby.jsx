import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import Navbar from '../components/Navbar';
import { getGames, createGame } from '../api';

const Lobby = () => {
  const [games, setGames] = useState([]);
  const [newGameName, setNewGameName] = useState('');
  const navigate = useNavigate();

  useEffect(() => {
    fetchGames();
  }, []);

  const fetchGames = async () => {
    try {
      const res = await getGames();
      setGames(res.data);
    } catch (err) {
      console.error(err);
    }
  };

  const handleCreateGame = async (e) => {
    e.preventDefault();
    try {
      const res = await createGame(newGameName);
      navigate(`/game/${res.data.id}`);
    } catch (err) {
      console.error(err);
    }
  };

  const handleJoinGame = (gameId) => {
    navigate(`/game/${gameId}`);
  };

  return (
    <div className="h-full flex flex-col overflow-hidden">
      <Navbar />

      <div className="flex-1 bg-gray-100 p-4 flex flex-col gap-4 justify-center items-center overflow-hidden">
        <div className="w-full max-w-md flex flex-col gap-4">
          <form onSubmit={handleCreateGame} className="flex gap-2">
            <input
              type="text"
              placeholder="新游戏名称"
              className="border p-3 rounded outline-blue-500 flex-grow"
              value={newGameName}
              onChange={(e) => setNewGameName(e.target.value)}
              required
            />
            <button className="bg-blue-600 text-white p-3 rounded font-bold hover:bg-blue-700 transition active:scale-95">
              创建
            </button>
          </form>

          <div className="flex flex-col gap-2">
            {games.map((game) => (
              <div
                key={game.id}
                onClick={() => handleJoinGame(game.id)}
                className="bg-white p-4 rounded-lg shadow-md cursor-pointer hover:bg-gray-200 transition flex justify-between items-center"
              >
                <span>{game.name}</span>
                <span>{game.players} / 4</span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};

export default Lobby;
