<?php
// backend/core/CardComparator.php

class CardComparator {

    const TYPE_HIGH_CARD = 1;
    const TYPE_PAIR = 2;
    const TYPE_TWO_PAIR = 3;
    const TYPE_TRIPS = 4;
    const TYPE_STRAIGHT = 5;
    const TYPE_FLUSH = 6;
    const TYPE_FULL_HOUSE = 7;
    const TYPE_QUADS = 8;
    const TYPE_SF = 9;

    private static $rankMap = [
        '2'=>2, '3'=>3, '4'=>4, '5'=>5, '6'=>6, '7'=>7, '8'=>8, '9'=>9, '10'=>10, 
        'jack'=>11, 'queen'=>12, 'king'=>13, 'ace'=>14
    ];

    private static $suitMap = [
        'spades' => 4, 'hearts' => 3, 'clubs' => 2, 'diamonds' => 1
    ];

    /**
     * 检查是否“相公” (不合规摆法)
     * 十三水规则：后墩 >= 中墩 >= 头墩
     */
    public static function isIllegal($front, $mid, $back) {
        $f = self::analyzeHand($front);
        $m = self::analyzeHand($mid);
        $b = self::analyzeHand($back);

        // 中墩比后墩大 -> 相公
        if (self::compare($m, $b) > 0) return true;
        // 头墩比中墩大 -> 相公
        if (self::compare($f, $m) > 0) return true;

        return false;
    }

    public static function compare($infoA, $infoB) {
        if ($infoA['type'] > $infoB['type']) return 1;
        if ($infoA['type'] < $infoB['type']) return -1;
        return self::compareSameType($infoA, $infoB);
    }

    public static function getScore($info, $lane) {
        $type = $info['type'];
        if ($lane === 'front') {
            if ($type === self::TYPE_TRIPS) return 3;
        } 
        elseif ($lane === 'mid') {
            if ($type === self::TYPE_SF) return 10;
            if ($type === self::TYPE_QUADS) return 8;
            if ($type === self::TYPE_FULL_HOUSE) return 2;
        } 
        elseif ($lane === 'back') {
            if ($type === self::TYPE_SF) return 5;
            if ($type === self::TYPE_QUADS) return 4;
        }
        return 1;
    }

    public static function analyzeHand($hand) {
        if (empty($hand)) return ['type' => 0];
        $cards = [];
        foreach ($hand as $c) {
            $val = isset($c['val']) ? $c['val'] : self::$rankMap[$c['rank']];
            $suitVal = isset($c['suit_val']) ? $c['suit_val'] : (isset(self::$suitMap[$c['suit']]) ? self::$suitMap[$c['suit']] : 0);
            $cards[] = ['val' => $val, 'suit' => $suitVal];
        }
        usort($cards, function($a, $b) { return $b['val'] - $a['val']; });

        $isFlush = (count($cards) == 5) && self::isFlush($cards);
        $straightRank = (count($cards) == 5) ? self::getStraightRank($cards) : 0;
        
        $counts = [];
        foreach ($cards as $c) $counts[$c['val']] = ($counts[$c['val']] ?? 0) + 1;
        
        if ($isFlush && $straightRank > 0) return ['type' => self::TYPE_SF, 'rank' => $straightRank, 'cards' => $cards];
        if (max($counts) == 4) {
            $quadVal = array_search(4, $counts);
            return ['type' => self::TYPE_QUADS, 'main' => $quadVal, 'kickers' => self::getKickers($cards, [$quadVal])];
        }
        if (in_array(3, $counts) && in_array(2, $counts)) {
            $tripVal = array_search(3, $counts);
            return ['type' => self::TYPE_FULL_HOUSE, 'main' => $tripVal]; 
        }
        if ($isFlush) return ['type' => self::TYPE_FLUSH, 'cards' => $cards];
        if ($straightRank > 0) return ['type' => self::TYPE_STRAIGHT, 'rank' => $straightRank, 'cards' => $cards];
        if (max($counts) == 3) {
            $tripVal = array_search(3, $counts);
            return ['type' => self::TYPE_TRIPS, 'main' => $tripVal, 'kickers' => self::getKickers($cards, [$tripVal])];
        }
        $pairs = array_keys($counts, 2);
        if (count($pairs) == 2) {
            rsort($pairs);
            return ['type' => self::TYPE_TWO_PAIR, 'main' => $pairs, 'kickers' => self::getKickers($cards, $pairs)];
        }
        if (count($pairs) == 1) return ['type' => self::TYPE_PAIR, 'main' => $pairs[0], 'kickers' => self::getKickers($cards, $pairs)];
        return ['type' => self::TYPE_HIGH_CARD, 'cards' => $cards];
    }

    private static function compareSameType($A, $B) {
        $type = $A['type'];
        if (in_array($type, [self::TYPE_QUADS, self::TYPE_FULL_HOUSE, self::TYPE_TRIPS, self::TYPE_PAIR])) {
            if ($A['main'] > $B['main']) return 1;
            if ($A['main'] < $B['main']) return -1;
            return self::compareKickers($A['kickers'] ?? [], $B['kickers'] ?? []);
        }
        if ($type === self::TYPE_TWO_PAIR) {
            if ($A['main'][0] > $B['main'][0]) return 1;
            if ($A['main'][0] < $B['main'][0]) return -1;
            if ($A['main'][1] > $B['main'][1]) return 1;
            if ($A['main'][1] < $B['main'][1]) return -1;
            return self::compareKickers($A['kickers'], $B['kickers']);
        }
        if ($type === self::TYPE_FLUSH || $type === self::TYPE_HIGH_CARD) {
            return self::compareCardsOneByOne($A['cards'], $B['cards']);
        }
        if ($type === self::TYPE_STRAIGHT || $type === self::TYPE_SF) {
            if ($A['rank'] > $B['rank']) return 1;
            if ($A['rank'] < $B['rank']) return -1;
            $maxCardA = self::getStraightMaxCard($A['cards'], $A['rank']);
            $maxCardB = self::getStraightMaxCard($B['cards'], $B['rank']);
            return ($maxCardA['suit'] > $maxCardB['suit']) ? 1 : -1;
        }
        return 0;
    }

    private static function compareCardsOneByOne($cardsA, $cardsB) {
        $len = min(count($cardsA), count($cardsB));
        for ($i = 0; $i < $len; $i++) {
            if ($cardsA[$i]['val'] > $cardsB[$i]['val']) return 1;
            if ($cardsA[$i]['val'] < $cardsB[$i]['val']) return -1;
        }
        if (count($cardsA) > 0 && count($cardsB) > 0) {
            $lastA = $cardsA[count($cardsA)-1];
            $lastB = $cardsB[count($cardsB)-1];
            if ($lastA['suit'] > $lastB['suit']) return 1;
            if ($lastA['suit'] < $lastB['suit']) return -1;
        }
        return 0;
    }

    private static function compareKickers($kickersA, $kickersB) {
        return self::compareCardsOneByOne($kickersA, $kickersB);
    }

    private static function isFlush($cards) {
        if (count($cards) < 5) return false;
        $s = $cards[0]['suit'];
        foreach ($cards as $c) if ($c['suit'] != $s) return false;
        return true;
    }

    private static function getStraightRank($cards) {
        if (count($cards) < 5) return 0;
        $vals = array_column($cards, 'val');
        if ($vals[0]==14 && $vals[1]==5 && $vals[2]==4 && $vals[3]==3 && $vals[4]==2) return 13.5; 
        for ($i=0; $i<4; $i++) if ($vals[$i] != $vals[$i+1] + 1) return 0;
        return $vals[0]; 
    }

    private static function getStraightMaxCard($cards, $rank) {
        return ($rank == 13.5) ? $cards[1] : $cards[0];
    }

    private static function getKickers($cards, $excludeVals) {
        $res = [];
        foreach ($cards as $c) if (!in_array($c['val'], $excludeVals)) $res[] = $c;
        return $res;
    }
}
?>