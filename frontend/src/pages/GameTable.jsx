import React, { useState, useEffect } from 'react';
import { getHand, submitHand } from '../api';
import Card from '../components/Card';

const GameTable = () => {
  const [session, setSession] = useState(null);
  const [cards, setCards] = useState([]);
  const [solutions, setSolutions] = useState([]);
  const [arranged, setArranged] = useState({ front: [], mid: [], back: [] });
  const [loading, setLoading] = useState(true);
  const [roundInfo, setRoundInfo] = useState("");
  const [deckId, setDeckId] = useState(null);

  useEffect(() => { fetchHand(); }, []);

  const fetchHand = async () => {
    setLoading(true);
    const res = await getHand();
    if (res.status === 'success') {
      setSession(res.session_id);
      setCards(res.cards);
      setSolutions(res.solutions);
      setRoundInfo(res.round_info);
      setDeckId(res.deck_id);
      setArranged({ front: [], mid: [], back: [] });
    } else if (res.status === 'finished') {
      window.location.href = '/lobby';
    }
    setLoading(false);
  };

  const useSolution = (sol) => {
    setArranged({
      front: sol.front,
      mid: sol.mid,
      back: sol.back
    });
    setCards([]); // 清空备选池
  };

  const selectCard = (card, lane) => {
    if (lane === 'pool') {
      if (arranged.front.length < 3) setArranged({...arranged, front: [...arranged.front, card]});
      else if (arranged.mid.length < 5) setArranged({...arranged, mid: [...arranged.mid, card]});
      else if (arranged.back.length < 5) setArranged({...arranged, back: [...arranged.back, card]});
      setCards(cards.filter(c => c !== card));
    } else {
      setArranged({...arranged, [lane]: arranged[lane].filter(c => c !== card)});
      setCards([...cards, card]);
    }
  };

  const handleSubmit = async () => {
    setLoading(true);
    const res = await submitHand({ session_id: session, deck_id: deckId, arranged });
    if (res.status === 'success') {
      fetchHand();
    } else {
      alert(res.message);
      setLoading(false);
    }
  };

  if (loading) return <div className="p-10 text-white text-center">处理中...</div>;

  return (
    <div className="min-h-screen bg-slate-900 p-4 flex flex-col items-center">
      <div className="text-emerald-400 font-mono mb-4 text-xl">{roundInfo}</div>

      {/* 推荐方案 */}
      <div className="flex gap-2 mb-6">
        {solutions.map((sol, idx) => (
          <button 
            key={idx}
            onClick={() => useSolution(sol)}
            className="bg-slate-700 hover:bg-slate-600 text-white text-xs px-3 py-2 rounded-lg border border-slate-500"
          >
            推荐摆法 {idx + 1}
          </button>
        ))}
      </div>

      {/* 摆牌区 */}
      <div className="w-full max-w-md bg-slate-800 rounded-2xl p-6 shadow-2xl space-y-6 border border-slate-700">
        {['front', 'mid', 'back'].map(lane => (
          <div key={lane}>
            <div className="text-[10px] uppercase tracking-widest text-slate-400 mb-2">{lane === 'front' ? '头墩' : lane === 'mid' ? '中墩' : '尾墩'}</div>
            <div className="flex justify-center min-h-[100px] bg-slate-900/50 rounded-xl p-2 items-center">
              {arranged[lane].map((c, i) => (
                <div key={i} onClick={() => selectCard(c, lane)} className="cursor-pointer -ml-8 first:ml-0 hover:-translate-y-2 transition-transform">
                  <Card rank={c.rank} suit={c.suit} />
                </div>
              ))}
            </div>
          </div>
        ))}
      </div>

      {/* 备选区 */}
      <div className="mt-8 flex flex-wrap justify-center gap-2 max-w-xl">
        {cards.map((c, i) => (
          <div key={i} onClick={() => selectCard(c, 'pool')} className="cursor-pointer hover:scale-110 transition-transform">
            <Card rank={c.rank} suit={c.suit} />
          </div>
        ))}
      </div>

      <button 
        onClick={handleSubmit}
        className="mt-12 w-full max-w-md bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-black py-4 rounded-xl shadow-[0_0_20px_rgba(16,185,129,0.4)] transition-all active:scale-95"
      >
        确认提交
      </button>
    </div>
  );
};

export default GameTable;
