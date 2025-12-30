import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { getGame, makeMove } from '../api';
import Navbar from '../components/Navbar';

const GameTable = () => {
  const [gameState, setGameState] = useState(null);
  const [move, setMove] = useState('');
  const [loading, setLoading] = useState(true);
  const { gameId } = useParams();

  useEffect(() => {
    fetchGameState();
  }, [gameId]);

  const fetchGameState = async () => {
    setLoading(true);
    try {
      const res = await getGame(gameId);
      setGameState(res.data);
    } catch (err) {
      console.error(err);
    }
    setLoading(false);
  };

  const handleMakeMove = async (e) => {
    e.preventDefault();
    try {
      await makeMove(gameId, move);
      setMove('');
      fetchGameState(); // Refresh game state after move
    } catch (err) {
      console.error(err);
    }
  };

  if (loading) {
    return <div className="h-full flex flex-col overflow-hidden">Loading...</div>;
  }

  return (
    <div className="h-full flex flex-col overflow-hidden">
      <Navbar />
      <div className="flex-1 bg-gray-100 p-4 flex flex-col gap-4 justify-center items-center overflow-hidden">
        <div className="w-full max-w-4xl bg-white p-4 rounded-lg shadow-md">
          <h2 className="text-xl font-bold mb-4">Game State</h2>
          <pre className="bg-gray-200 p-4 rounded">
            {JSON.stringify(gameState, null, 2)}
          </pre>
        </div>

        <form onSubmit={handleMakeMove} className="w-full max-w-4xl flex gap-2 mt-4">
          <input
            type="text"
            placeholder="Enter your move"
            className="border p-3 rounded outline-blue-500 flex-grow"
            value={move}
            onChange={(e) => setMove(e.target.value)}
            required
          />
          <button className="bg-blue-600 text-white p-3 rounded font-bold hover:bg-blue-700 transition active:scale-95">
            Make Move
          </button>
        </form>
      </div>
    </div>
  );
};

export default GameTable;
