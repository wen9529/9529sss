// frontend/src/components/Card.jsx
import React from 'react';

const Card = ({ card, onClick, selected, style }) => {
  const imgSrc = `/cards/${card.img}`;

  return (
    <div 
      onClick={(e) => {
        e.stopPropagation();
        onClick();
      }} 
      style={style}
      className={`
        relative 
        /* --- 核心修改：放大尺寸 --- */
        w-24 sm:w-32 
        aspect-[2/3]
        
        /* --- 核心修改：左对齐堆叠 (负边距) --- */
        -ml-16 sm:-ml-20 
        first:ml-0 
        
        /* 样式细节 */
        rounded-lg 
        shadow-[2px_0_8px_rgba(0,0,0,0.4)] 
        transition-transform duration-150 
        cursor-pointer 
        select-none 
        bg-white
        
        /* 悬停效果 */
        hover:z-20 hover:-translate-y-2
        
        ${selected 
          ? 'border-2 border-red-600 z-30 -translate-y-6 shadow-2xl' // 选中时上浮更明显
          : 'border border-gray-400'
        }
      `}
    >
      {/* 图片强制撑满，不留白边 */}
      <img 
        src={imgSrc} 
        alt={`${card.rank} of ${card.suit}`}
        className="w-full h-full object-cover rounded-md pointer-events-none block" 
      />
    </div>
  );
};

export default Card;