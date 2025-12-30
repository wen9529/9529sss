<?php
// backend/core/Logic.php

require_once __DIR__ . '/CardComparator.php';
require_once __DIR__ . '/SpecialHandEvaluator.php';

class GameLogic {
    
    public static function generateDeckOrder() {
        $order = range(1, 320);
        shuffle($order);
        return array_slice($order, 0, 20);
    }

    /**
     * 四人比牌逻辑
     * @param array $playerHands [user_id => ['front'=>[], 'mid'=>[], 'back'=>[]]]
     * @return array [user_id => score_change]
     */
    public static function settleFourPlayers($playerHands) {
        $userIds = array_keys($playerHands);
        $scores = array_fill_keys($userIds, 0);
        
        // 1. 解析所有人的每一道牌型
        $details = [];
        foreach ($playerHands as $uid => $hand) {
            // 检查特殊牌型
            $allCards = array_merge($hand['front'], $hand['mid'], $hand['back']);
            $spScore = SpecialHandEvaluator::evaluate($allCards);
            
            $details[$uid] = [
                'is_special' => ($spScore > 0),
                'special_points' => $spScore,
                'front' => CardComparator::analyzeHand($hand['front']),
                'mid'   => CardComparator::analyzeHand($hand['mid']),
                'back'  => CardComparator::analyzeHand($hand['back']),
            ];
        }

        // 2. 两两对战 (AB, AC, AD, BC, BD, CD)
        for ($i = 0; $i < count($userIds); $i++) {
            for ($j = $i + 1; $j < count($userIds); $j++) {
                $u1 = $userIds[$i];
                $u2 = $userIds[$j];
                
                $result = self::compareTwoPlayers($details[$u1], $details[$u2]);
                
                $scores[$u1] += $result['u1_score'];
                $scores[$u2] += $result['u2_score'];
            }
        }

        return $scores;
    }

    /**
     * 两人对战比分
     */
    private static function compareTwoPlayers($p1, $p2) {
        // 如果有人是特殊牌型
        if ($p1['is_special'] || $p2['is_special']) {
            if ($p1['is_special'] && $p2['is_special']) {
                if ($p1['special_points'] > $p2['special_points']) {
                    return ['u1_score' => $p1['special_points'], 'u2_score' => -$p1['special_points']];
                } elseif ($p1['special_points'] < $p2['special_points']) {
                    return ['u1_score' => -$p2['special_points'], 'u2_score' => $p2['special_points']];
                }
                return ['u1_score' => 0, 'u2_score' => 0];
            }
            if ($p1['is_special']) return ['u1_score' => $p1['special_points'], 'u2_score' => -$p1['special_points']];
            return ['u1_score' => -$p2['special_points'], 'u2_score' => $p2['special_points']];
        }

        // 普通牌型：三道分别比对
        $lanes = ['front', 'mid', 'back'];
        $u1_wins = 0;
        $u2_wins = 0;
        $total_diff = 0;

        foreach ($lanes as $lane) {
            $cmp = CardComparator::compare($p1[$lane], $p2[$lane]);
            if ($cmp > 0) {
                $u1_wins++;
                $total_diff += CardComparator::getScore($p1[$lane], $lane);
            } elseif ($cmp < 0) {
                $u2_wins++;
                $total_diff -= CardComparator::getScore($p2[$lane], $lane);
            }
        }

        // 全垒打 (Scoop) 翻倍
        if ($u1_wins === 3) $total_diff *= 2;
        if ($u2_wins === 3) $total_diff *= 2;

        return ['u1_score' => $total_diff, 'u2_score' => -$total_diff];
    }
}
?>