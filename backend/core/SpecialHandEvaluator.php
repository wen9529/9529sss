<?php
// backend/core/SpecialHandEvaluator.php

class SpecialHandEvaluator {

    // --- 特殊牌型分值 (根据需求定义) ---
    const SP_DRAGON = 26;         // 至尊青龙/一条龙
    const SP_THREE_SF = 16;       // 三同花顺 (包含同花顺的三顺子/三同花)
    const SP_QUADS_6PAIR = 14;    // 四条六对半 (六对半中包含四条)
    
    const SP_THREE_FLUSH = 6;     // 三同花
    const SP_THREE_STRAIGHT = 6;  // 三顺子
    const SP_SIX_PAIRS = 6;       // 六对半
    
    const SP_NONE = 0;

    private static $rankMap = [
        '2'=>2, '3'=>3, '4'=>4, '5'=>5, '6'=>6, '7'=>7, '8'=>8, '9'=>9, '10'=>10, 
        'jack'=>11, 'queen'=>12, 'king'=>13, 'ace'=>14
    ];

    /**
     * 评估手牌是否为特殊牌型
     * @param array $cards 13张牌 [{rank, suit}, ...]
     * @return int 分数 (0表示非特殊牌型)
     */
    public static function evaluate($cards) {
        // 预处理：提取点数和花色
        $vals = [];
        $suits = [];
        foreach ($cards as $c) {
            $vals[] = isset($c['val']) ? $c['val'] : self::$rankMap[$c['rank']];
            $suits[] = $c['suit'];
        }
        sort($vals); // 从小到大排序

        // ---------------------------------------------
        // 1. 一条龙 (2,3,4,5,6,7,8,9,10,11,12,13,14)
        // ---------------------------------------------
        $isDragon = true;
        for ($i=0; $i<13; $i++) {
            // vals[0] 应该是 2, vals[12] 应该是 14
            if ($vals[$i] != $i+2) $isDragon = false;
        }
        if ($isDragon) return self::SP_DRAGON;

        // 统计点数频率
        $counts = array_count_values($vals);
        $pairCount = 0;
        $quadCount = 0;
        $tripCount = 0;
        foreach ($counts as $v => $cnt) {
            if ($cnt >= 2) $pairCount++; // 注意：4张算1个“对子来源”？这里逻辑需要严谨
            if ($cnt == 4) $quadCount++;
            if ($cnt == 3) $tripCount++;
        }

        // ---------------------------------------------
        // 2. 六对半 (6对+1散)
        // ---------------------------------------------
        // 严谨判定：对子总数 = 6
        // 比如 2,2, 3,3, 4,4, 5,5, 6,6, 7,7, 8 -> 6对
        // 比如 2,2,2,2, 3,3, 4,4, 5,5, 6,6, 7 -> 6对 (4张算2对)
        $realPairs = 0;
        foreach ($counts as $cnt) {
            $realPairs += floor($cnt / 2);
        }
        
        if ($realPairs == 6) {
            // 如果包含四条 (如 2222 33 44 55 66 7)，则是四条六对半
            if ($quadCount > 0) return self::SP_QUADS_6PAIR;
            return self::SP_SIX_PAIRS;
        }

        // ---------------------------------------------
        // 3. 三同花 (3张 + 5张 + 5张 同花)
        // ---------------------------------------------
        // 判据：花色分布满足 (>=8, >=5) 或 (>=5, >=5, >=3)
        $suitCounts = array_count_values($suits);
        rsort($suitCounts);
        $isThreeFlush = false;
        
        // 情况A: 5, 5, 3 (及以上)
        if (isset($suitCounts[2]) && $suitCounts[0]>=5 && $suitCounts[1]>=5 && $suitCounts[2]>=3) $isThreeFlush = true;
        // 情况B: 8, 5 (及以上)
        if (isset($suitCounts[1]) && $suitCounts[0]>=8 && $suitCounts[1]>=5) $isThreeFlush = true;
        // 情况C: 13张同色 (至尊清龙归为三同花变体，或上面已判定一条龙)
        if ($suitCounts[0] == 13) $isThreeFlush = true;

        if ($isThreeFlush) {
            // 检查是否含同花顺 (三同花顺)
            // 这是一个简化的检查：如果这13张牌里能凑出至少一个同花顺，就给高分
            // 实际规则可能要求 3道全是同花顺？或者只要含有一个？
            // 根据你的描述："三同花或者三顺子中带有同花顺的牌型是16分" -> 只要有一个同花顺即可
            if (self::hasStraightFlush($cards)) return self::SP_THREE_SF;
            
            return self::SP_THREE_FLUSH; 
        }

        // ---------------------------------------------
        // 4. 三顺子 (3张 + 5张 + 5张 顺子)
        // ---------------------------------------------
        // 判据：13张牌能凑成 3+5+5 的顺子。
        // 这是一个背包问题。为了性能，这里使用贪心检测：
        // 尝试提取最长的顺子，看剩下的是否能组顺子。
        if (self::checkThreeStraights($vals)) {
            // 检查同花顺
            if (self::hasStraightFlush($cards)) return self::SP_THREE_SF;
            return self::SP_THREE_STRAIGHT;
        }
        
        return self::SP_NONE;
    }

    // --- 辅助：检测是否存在至少一个同花顺 ---
    private static function hasStraightFlush($cards) {
        // 按花色分组
        $bySuit = [];
        foreach ($cards as $c) $bySuit[$c['suit']][] = $c['val'];
        
        foreach ($bySuit as $suitVals) {
            if (count($suitVals) < 5) continue;
            // 去重并排序
            $vals = array_unique($suitVals);
            sort($vals);
            // 找连续5个
            $consecutive = 0;
            $last = -99;
            foreach ($vals as $v) {
                if ($v == $last + 1) $consecutive++;
                else $consecutive = 1;
                if ($consecutive >= 5) return true;
                $last = $v;
            }
            // 检查 A2345 (A=14, 2,3,4,5)
            // 只要包含 14,2,3,4,5
            $hasA2345 = (in_array(14, $vals) && in_array(2, $vals) && in_array(3, $vals) && in_array(4, $vals) && in_array(5, $vals));
            if ($hasA2345) return true;
        }
        return false;
    }

    // --- 辅助：检测三顺子 (简易回溯法) ---
    private static function checkThreeStraights($vals) {
        // $vals 是 13 个数字 (2-14)
        sort($vals);
        
        // 尝试寻找 5 + 5 + 3
        // 我们可以尝试暴力提取所有的 5张顺子组合
        // 由于 PHP 单线程且这是在请求中运行，不能太慢。
        // 简化策略：如果不缺牌（比如没有空缺），大概率是顺子
        // 精确策略：回溯
        return self::solveStraights($vals, [5, 5, 3]);
    }

    private static function solveStraights($pool, $targets) {
        if (empty($targets)) return true; // 全部满足
        
        $len = array_shift($targets); // 当前需要凑的顺子长度 (5 或 3)
        
        // 尝试在 pool 中找一个长度为 len 的顺子
        // 1. 获取去重后的点数
        $uniqueVals = array_unique($pool);
        sort($uniqueVals);
        
        // 遍历所有可能的顺子起点
        foreach ($uniqueVals as $start) {
            // 检查是否能构成顺子: start, start+1, ... start+len-1
            $needed = [];
            for ($i=0; $i<$len; $i++) $needed[] = $start + $i;
            
            // A2345 特殊处理 (如果 len=5)
            // 这里的 $pool 里 A是14。如果要凑 12345? 不行，十三水最小是2。
            // 所以 A2345 是 14, 2, 3, 4, 5。
            // 这里简化逻辑：暂只支持普通顺子，A2345 逻辑略复杂，视需求添加。
            
            // 检查 pool 是否包含 needed
            if (self::containsAll($pool, $needed)) {
                // 移除这几张牌，递归下一层
                $newPool = self::removeCards($pool, $needed);
                if (self::solveStraights($newPool, $targets)) return true;
            }
        }
        
        return false;
    }

    private static function containsAll($pool, $needed) {
        $poolCounts = array_count_values($pool);
        foreach ($needed as $v) {
            if (!isset($poolCounts[$v]) || $poolCounts[$v] <= 0) return false;
            $poolCounts[$v]--;
        }
        return true;
    }

    private static function removeCards($pool, $toRemove) {
        foreach ($toRemove as $v) {
            $key = array_search($v, $pool);
            if ($key !== false) {
                unset($pool[$key]);
            }
        }
        return array_values($pool);
    }
}
?>