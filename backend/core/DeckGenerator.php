<?php
// backend/core/DeckGenerator.php

class DeckGenerator {
    
    // 内部评分权重 (必须与 CardComparator 逻辑一致)
    // 数字越大牌型越大，用于 AI 排序和防倒水检查
    const SCORE_HIGH_CARD = 100000;
    const SCORE_PAIR      = 200000;
    const SCORE_TWO_PAIR  = 300000;
    const SCORE_TRIPS     = 400000;
    const SCORE_STRAIGHT  = 500000;
    const SCORE_FLUSH     = 600000; // 同花 > 顺子
    const SCORE_FULL_HOUSE= 700000;
    const SCORE_QUADS     = 800000;
    const SCORE_SF        = 900000; 

    private static $rankMap = [
        '2'=>2, '3'=>3, '4'=>4, '5'=>5, '6'=>6, '7'=>7, '8'=>8, '9'=>9, '10'=>10, 
        'jack'=>11, 'queen'=>12, 'king'=>13, 'ace'=>14
    ];

    public static function fill($pdo, $count) {
        $suits = ['spades', 'hearts', 'clubs', 'diamonds'];
        $ranks = ['2','3','4','5','6','7','8','9','10','jack','queen','king','ace'];
        
        $stmt = $pdo->prepare("INSERT INTO pre_decks (cards_json, solutions_json) VALUES (:cards, :solutions)");

        echo "正在使用【同步版 AI】生成 $count 局...\n";

        for ($i = 0; $i < $count; $i++) {
            $deck = [];
            foreach ($suits as $suit) {
                foreach ($ranks as $rank) {
                    $deck[] = [
                        'suit'=>$suit, 'rank'=>$rank, 'val' => self::$rankMap[$rank], 'img'=>"{$rank}_of_{$suit}.svg"
                    ];
                }
            }
            shuffle($deck);
            $hands = [array_slice($deck,0,13), array_slice($deck,13,13), array_slice($deck,26,13), array_slice($deck,39,13)];

            $solutions = [];
            foreach ($hands as $hand) {
                $solutions[] = self::getValidSolutions($hand);
            }

            $stmt->execute([':cards' => json_encode($hands), ':solutions' => json_encode($solutions)]);
            if (($i+1) % 20 == 0) echo ".";
        }
        echo "\n完成。\n";
    }

    private static function getValidSolutions($hand) {
        // 1. 贪心算法 (主攻同花/铁支)
        $sol1 = self::solveGreedy($hand);
        $sol1['desc'] = "进攻型";

        // 2. 均衡算法
        // 尝试优先保留对子，或者不同的排序输入
        $handSort = $hand;
        usort($handSort, function($a, $b) { return $a['val'] - $b['val']; });
        $sol2 = self::solveGreedy($handSort); 
        $sol2['desc'] = "均衡型";

        // 3. 绝对防守 (保底不倒水)
        $sol3 = self::solveSafety($hand);
        $sol3['desc'] = "稳健防倒水";

        // 强制修正所有方案 (这是关键，用最新的评分标准检查)
        return [
            self::fixWater($sol1),
            self::fixWater($sol2),
            self::fixWater($sol3)
        ];
    }

    // --- 核心：保底防倒水生成器 ---
    private static function solveSafety($cards) {
        // 1. 选出最好的5张给 Back
        $bestBack = self::findBest5($cards);
        $back = $bestBack['cards'];
        $remain = self::diffCards($cards, $back);

        // 2. 选出剩下最好的5张给 Mid
        $bestMid = self::findBest5($remain);
        $mid = $bestMid['cards'];
        $front = self::diffCards($remain, $mid); // 剩下的3张给 Front

        return ['front' => array_values($front), 'mid' => array_values($mid), 'back' => array_values($back)];
    }

    // --- 核心：强制倒水修正 ---
    private static function fixWater($sol) {
        // 计算三道分数 (使用最新的规则)
        $sF = self::getScore($sol['front']);
        $sM = self::getScore($sol['mid']);
        $sB = self::getScore($sol['back']);

        // 1. 检查 中道 > 尾道
        if ($sM > $sB) {
            // 交换中尾
            $temp = $sol['mid'];
            $sol['mid'] = $sol['back'];
            $sol['back'] = $temp;
            // 交换后分数变化，重新赋值
            $tempS = $sM; $sM = $sB; $sB = $tempS;
        }

        // 2. 检查 头道 > 中道
        // 注意：这里要非常小心。头道只有3张，中道5张。
        // 如果头道分 > 中道分，必须处理。
        if ($sF > $sM) {
            // 重新调用 Safety 算法，强行按大小分配
            $all = array_merge($sol['front'], $sol['mid'], $sol['back']);
            return self::solveSafety($all); 
        }

        return $sol;
    }

    // --- 贪心查找 ---
    private static function solveGreedy($cards) {
        $bestBack = self::findBest5($cards);
        $back = $bestBack['cards'];
        $remain = self::diffCards($cards, $back);
        
        $bestMid = self::findBest5($remain);
        $mid = $bestMid['cards'];
        $front = self::diffCards($remain, $mid);

        return ['front' => array_values($front), 'mid' => array_values($mid), 'back' => array_values($back)];
    }

    // --- 评分系统 (与 CardComparator 对齐) ---
    private static function getScore($cards) {
        usort($cards, function($a, $b) { return $b['val'] - $a['val']; }); // 大到小
        $count = count($cards);
        $maxVal = $cards[0]['val'];

        // --- 头道评分 ---
        if ($count == 3) {
            // 三条
            if ($cards[0]['val'] == $cards[1]['val'] && $cards[1]['val'] == $cards[2]['val']) 
                return self::SCORE_TRIPS + $maxVal;
            // 对子
            if ($cards[0]['val'] == $cards[1]['val']) 
                return self::SCORE_PAIR + $cards[0]['val']; // 对子在头
            if ($cards[1]['val'] == $cards[2]['val']) 
                return self::SCORE_PAIR + $cards[1]['val'];
            // 乌龙
            return self::SCORE_HIGH_CARD + $maxVal;
        }

        // --- 5张评分 ---
        $isFlush = true;
        $suit = $cards[0]['suit'];
        foreach($cards as $c) if($c['suit'] != $suit) $isFlush = false;

        // 顺子检测 (含 A2345)
        $isStraight = true;
        $vals = array_column($cards, 'val');
        // 检查特殊顺子 A,5,4,3,2
        $isA2345 = ($vals[0]==14 && $vals[1]==5 && $vals[2]==4 && $vals[3]==3 && $vals[4]==2);
        
        if (!$isA2345) {
            for($i=0; $i<4; $i++) {
                if($vals[$i] != $vals[$i+1] + 1) $isStraight = false;
            }
        }

        // 分数计算
        if ($isFlush && $isStraight) return self::SCORE_SF + $maxVal;
        if ($isFlush) return self::SCORE_FLUSH + $maxVal; // 同花 > 顺子
        
        if ($isStraight) {
            // 如果是 A2345，它的等级是 13.5 (仅次于 10JQKA)
            // 普通顺子等级是 maxVal
            $rank = $isA2345 ? 13.5 : $maxVal;
            return self::SCORE_STRAIGHT + $rank;
        }

        // 统计
        $counts = [];
        foreach($cards as $c) $counts[$c['val']] = ($counts[$c['val']] ?? 0) + 1;
        rsort($counts); 

        if ($counts[0] == 4) return self::SCORE_QUADS + $maxVal;
        if ($counts[0] == 3 && $counts[1] == 2) return self::SCORE_FULL_HOUSE + $maxVal;
        if ($counts[0] == 3) return self::SCORE_TRIPS + $maxVal;
        if ($counts[0] == 2 && $counts[1] == 2) return self::SCORE_TWO_PAIR + $maxVal;
        if ($counts[0] == 2) return self::SCORE_PAIR + $maxVal;

        return self::SCORE_HIGH_CARD + $maxVal;
    }

    // --- 找最好的5张 (AI 核心) ---
    private static function findBest5($pool) {
        usort($pool, function($a, $b) { return $b['val'] - $a['val']; });

        // 1. 同花 (Flush) - 优先级高
        $suits = [];
        foreach ($pool as $c) $suits[$c['suit']][] = $c;
        foreach ($suits as $sCards) {
            if (count($sCards) >= 5) {
                // 检查同花顺 (略，直接当同花返回，反正分高)
                return ['score' => self::SCORE_FLUSH, 'cards' => array_slice($sCards, 0, 5)];
            }
        }

        // 2. 铁支 (Quads)
        $counts = self::countRanks($pool);
        foreach ($counts as $val => $grp) {
            if (count($grp) == 4) {
                $kicker = null; foreach($pool as $c) { if($c['val'] != $val) { $kicker=$c; break; }}
                return ['score' => self::SCORE_QUADS, 'cards' => array_merge($grp, [$kicker])];
            }
        }

        // 3. 葫芦 (Full House)
        $trips = []; $pairs = [];
        foreach ($counts as $val => $grp) {
            if (count($grp) >= 3) $trips[] = $grp;
            elseif (count($grp) >= 2) $pairs[] = $grp;
        }
        if (!empty($trips)) {
            $main = $trips[0]; 
            $sub = null;
            if (!empty($pairs)) $sub = $pairs[0];
            elseif (count($trips) > 1) $sub = array_slice($trips[1], 0, 2);
            
            if ($main && $sub) {
                return ['score' => self::SCORE_FULL_HOUSE, 'cards' => array_merge($main, $sub)];
            }
        }

        // 4. 顺子 (Straight) - 含 A2345
        // 去重
        $uniqueVals = [];
        foreach ($pool as $c) $uniqueVals[$c['val']] = $c;
        ksort($uniqueVals); // 2..14
        $vals = array_keys($uniqueVals);
        
        // 检测普通顺子
        $consecutive = 0; $lastV = -1; $straightEnd = -1;
        foreach ($vals as $v) {
            if ($v == $lastV + 1) $consecutive++; else $consecutive = 1;
            if ($consecutive >= 5) $straightEnd = $v;
            $lastV = $v;
        }
        if ($straightEnd != -1) {
            $res = [];
            for ($j=0; $j<5; $j++) $res[] = $uniqueVals[$straightEnd - $j];
            return ['score' => self::SCORE_STRAIGHT, 'cards' => $res];
        }
        // 检测 A2345
        if (isset($uniqueVals[14]) && isset($uniqueVals[2]) && isset($uniqueVals[3]) && isset($uniqueVals[4]) && isset($uniqueVals[5])) {
            $res = [$uniqueVals[14], $uniqueVals[5], $uniqueVals[4], $uniqueVals[3], $uniqueVals[2]]; // A,5,4,3,2
            return ['score' => self::SCORE_STRAIGHT, 'cards' => $res];
        }

        // 5. 三条
        if (!empty($trips)) {
            $main = $trips[0];
            $kickers = [];
            foreach ($pool as $c) {
                if ($c['val'] != $main[0]['val'] && count($kickers) < 2) $kickers[] = $c;
            }
            return ['score' => self::SCORE_TRIPS, 'cards' => array_merge($main, $kickers)];
        }

        // 6. 两对
        if (count($pairs) >= 2) {
            $p1 = $pairs[0]; $p2 = $pairs[1];
            $kicker = null; foreach ($pool as $c) {
                if ($c['val'] != $p1[0]['val'] && $c['val'] != $p2[0]['val']) { $kicker=$c; break; }
            }
            return ['score' => self::SCORE_TWO_PAIR, 'cards' => array_merge($p1, $p2, [$kicker])];
        }

        // 7. 一对
        if (!empty($pairs)) {
            $p1 = $pairs[0];
            $kickers = [];
            foreach ($pool as $c) {
                if ($c['val'] != $p1[0]['val'] && count($kickers) < 3) $kickers[] = $c;
            }
            return ['score' => self::SCORE_PAIR, 'cards' => array_merge($p1, $kickers)];
        }

        // 8. 乌龙
        return ['score' => self::SCORE_HIGH_CARD, 'cards' => array_slice($pool, 0, 5)];
    }

    private static function countRanks($cards) {
        $counts = [];
        foreach ($cards as $c) $counts[$c['val']][] = $c;
        krsort($counts);
        return $counts;
    }

    private static function diffCards($all, $sub) {
        $res = [];
        foreach ($all as $c) {
            $found = false;
            foreach ($sub as $s) {
                if ($c['suit'] == $s['suit'] && $c['rank'] == $s['rank']) { $found = true; break; }
            }
            if (!$found) $res[] = $c;
        }
        return $res;
    }
}
?>