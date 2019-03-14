<?php

class Hand extends Base
{
    const STREET_PREFLOP = 'preflop';
    const STREET_FLOP = 'flop';
    const STREET_TURN = 'turn';
    const STREET_RIVER = 'river';

    const GAME_HOLDEM = 'holdem';

    const LIMIT_NOLIMIT = 'nolimit';

    const TABLE_SIZE_2 = 2;
    const TABLE_SIZE_6 = 6;
    const TABLE_SIZE_9 = 9;

    const SMALL_BLIND = 'sb';
    const BIG_BLIND = 'bb';

    const POST_SB = self::SMALL_BLIND;
    const POST_BB = self::BIG_BLIND;
    const POST_OTHER = 'other';
    const POST_RETURN = 'return';

    private $id;

    private $table_id;

    private $timestamp;

    private $game;

    private $limit;

    private $table_size;

    private $game_blinds;

    private $dealer_seat;

    private $players;

    private $posts;

    private $community_cards;

    private $all_action;

    private $pots;

    private $rake;

    private $summary_seats;

    public function __construct()
    {
	$this->game_blinds = array();

	$this->posts = array(
	    'sb' => array(),
	    'bb' => array(),
	    'other' => array(),
	    'return' => array()
	);

	$this->players = array();

	$this->community_cards = array(
	    self::STREET_FLOP => array(),
	    self::STREET_TURN => array(),
	    self::STREET_RIVER => array()
	);

	$this->action = array();

	$this->pots = array();

	$this->summary_seats = array();
    }

    public static function validateId($id)
    {
        if (! (is_numeric($id) || is_string($id)) || ! strlen($id))
            throw new InvalidArgumentException(sprintf("Invalid hand ID value", $id));

        return true;
    }

    public function setId($id)
    {
        $this->validateId($id);

	$this->id = $id;

	return $this;
    }

    public function getId()
    {
	return $this->id;
    }

    public static function validateTableId($table_id)
    {
        if (! is_string($table_id) || ! strlen($table_id))
            throw new InvalidArgumentException(sprintf("Invalid table ID value `%s'", $table_id));

        return true;
    }

    public function setTableId($table_id)
    {
        $this->validateTableId($table_id);

	$this->table_id = $table_id;

	return $this;
    }

    public function getTableId()
    {
	return $this->table_id;
    }

    public static function validateTimestamp($timestamp)
    {
        if (! is_int($timestamp) || $timestamp < 0)
            throw new InvalidArgumentException(sprintf("Incorrect timestamp `%s'", $timestamp));

        return true;
    }

    public function setTimestamp($timestamp)
    {
        $this->validateTimestamp($timestamp);

	$this->timestamp = $timestamp;

	return $this;
    }

    public function getTimestamp()
    {
    	return $this->timestamp;
    }

    public static function validateGame($game)
    {
        if (! in_array($game, array(self::GAME_HOLDEM)))
            throw new InvalidArgumentException(sprintf("Unsupported game `%s'", $game));

        return true;
    }

    public function setGame($game)
    {
        $this->validateGame($game);

	$this->game = $game;

	return $this;
    }

    public function getGame()
    {
	return $this->game;
    }

    public static function validateLimit($limit)
    {
        if (! in_array($limit, array(self::LIMIT_NOLIMIT)))
            throw new InvalidArgumentException(sprintf("Unsupported limit type `%s'", $limit));

        return true;
    }

    public function setLimit($limit)
    {
        $this->validateLimit($limit);

    	$this->limit = $limit;

	return $this;
    }

    public function getLimit()
    {
	return $this->limit;
    }

    public function getGameBlinds()
    {
	return $this->game_blinds;
    }

    public function getGameBlind($blind)
    {
        $this->validateBlindType($blind);

        $game_blinds = $this->getGameBlinds();

        return $game_blinds[$blind];
    }

    public static function validateBlindType($blind_type)
    {
        if (! in_array($blind_type, array(self::SMALL_BLIND, self::BIG_BLIND)))
            throw new InvalidArgumentException(sprintf("Invalid blind type `%s'", $blind_type));

        return true;
    }

    public function setGameBlind($blind_type, $chips)
    {
        $this->validateBlindType($blind_type);
        $this->validateChipsAmount($chips);

    	$this->game_blinds[$blind] = $chips;

        return $this;
    }

    public function setGameSb($chips)
    {
    	return $this->setGameBlind(self::SMALL_BLIND, $chips);
    }

    public function getGameSb()
    {
        return $this->getGameBlind(self::SMALL_BLIND);
    }

    public function setGameBb($chips)
    {
    	return $this->setGameBlind(self::BIG_BLIND, $chips);
    }

    public function getGameBb()
    {
        return $this->getGameBlind(self::BIG_BLIND);
    }

    public static function validateTableSize($table_size)
    {
        if (! in_array($table_size, array(self::TABLE_SIZE_2, self::TABLE_SIZE_6, self::TABLE_SIZE_9)))
            throw new InvalidArgumentException(sprintf("Unsupported table size `%s'", $table_size));

        return true;
    }

    public function setTableSize($table_size)
    {
        $this->validateTableSize($table_size);

	$this->table_size = $table_size;

	return $this;
    }

    public function getTableSize()
    {
	return $this->table_size;
    }

    public function createPlayer()
    {
	$player = new Player();
	$this->players[] = $player;

	return $player;
    }

    public function getPlayers()
    {
    	return $this->players;
    }

    public function getPlayer($name)
    {
        if (! isset($this->players[$name]))
            return false;

    	return $this->players[$name];
    }

    public function getDealerPlayer()
    {
	foreach ($this->getPlayers() as $player)
	    if ($player->getSeat() == $this->getDealerSeat())
		return $player;

	return false;
    }

    public function getHeroPlayer()
    {
	foreach ($this->getPlayers() as $player)
	    if ($player->getIsHero())
		return $player;

	return false;
    }

    public function getSeats()
    {
	$seats = array();

	foreach ($this->getPlayers() as $player)
	    $seats[$player->getSeat()] = $player;

	ksort($seats, SORT_NUMERIC);

    	return $seats;
    }

    public function getSeat($i)
    {
    	$seats = $this->getSeats();
	if (! isset($seats[$i]))
	    return false;

    	return $seats[$i];
    }

    public function setDealerSeat($dealer_seat)
    {
        if (! is_int($dealer_seat) || $dealer_seat < 1)
            throw new InvalidArgumentException(sprintf("Invalid dealer seat number `%i'", $dealer_seat));

    	$this->dealer_seat = $dealer_seat;

	return $this;
    }

    public function getDealerSeat()
    {
	return $this->dealer_seat;
    }

    public static function validatePostType($post_type)
    {
        if (! in_array($post_type, array(self::POST_SB, self::POST_BB, self::POST_OTHER, self::POST_RETURN)))
            throw new InvalidArgumentException(sprintf("Invalid post type `%s'", $post_type));

        return true;
    }

    public static function validatePlayerName($name)
    {
        if (empty($name))
            throw new InvalidArgumentException("Empty player name");

        return true;
    }

    public static function validateChipsAmount($chips)
    {
        if (! is_numeric($chips) || $chips < 0)
            throw new InvalidArgumentException(sprintf("Invalid chips amount, `%f'", $chips));

        return true;
    }

    public function addPost($type, $player, $chips)
    {
        $this->validatePostType($type);
        $this->validatePlayerName($player);
        $this->validateChipsAmount($chips);

	$post = array(
	    'player' => $player,
	    'chips' => $chips
	);

	if ($type == self::POST_OTHER)
	    $this->posts[$type][] = $post;
	else
	    $this->posts[$type] = $post;

	return $this;
    }

    public function getPosts()
    {
    	return $this->posts;
    }

    public function getPost($type)
    {
        if (! in_array($type, $this->getPostTypes()))
            throw new InvalidArgumentException(sprintf("Invalid post type `%s'", $type));

        $posts = $this->getPosts();

    	return $posts[$type];
    }

    public function getPostSb()
    {
    	return $this->getPost(self::POST_SB);
    }

    public function getPostBb()
    {
    	return $this->getPost(self::POST_BB);
    }

    public function getPostOther()
    {
    	return $this->getPost(self::POST_OTHER);
    }

    public function getPostReturn()
    {
    	return $this->getPost(self::POST_RETURN);
    }

    public static function validateCard($card)
    {
        if (empty($card))
            throw new InvalidArgumentException("Empty card string");

        if (! preg_match('/^[2-9TJQKA][cdhs]$/', $card))
            throw new InvalidArgumentException(sprintf("Invalid card string `%s'", $card));
    }

    public static function validateStreetPostflop($street)
    {
        if (! in_array($street, array(self::STREET_FLOP, self::STREET_TURN, self::STREET_RIVER)))
            throw new InvalidArgumentException(sprintf("Invalid street `%s'", $street));
    }

    public static function validateStreetCardCount($street, $card_count)
    {
        if (($street == self::STREET_FLOP && $card_count != 3)
         || ($street == self::STREET_TURN && $card_count != 1)
         || ($street == self::STREET_RIVER && $card_count != 1)) {
            throw new InvalidArgumentException(sprintf("Invalid nuber of community cards (%d) for street `%s'", $card_count, $street));
        }
    }

    public function setCommunityCards($street, $cards)
    {
        $this->validateStreetPostflop($street);
        $this->validateStreetCardCount($street, count($cards));

        foreach ($cards as $c)
            $this->validateCard($c);

    	$this->community_cards[$street] = $cards;

	return $this;
    }

    public function setFlopCommunityCards($cards)
    {
    	return $this->setCommunityCards(self::STREET_FLOP, $cards);
    }

    public function setTurnCommunityCards($cards)
    {
    	return $this->setCommunityCards(self::STREET_TURN, $cards);
    }

    public function setRiverCommunityCards($cards)
    {
    	return $this->setCommunityCards(self::STREET_RIVER, $cards);
    }

    public function getAllCommunityCards()
    {
	return array_merge($this->community_cards[self::STREET_FLOP],
	    $this->community_cards[self::STREET_TURN], $this->community_cards[self::STREET_RIVER]);
    }

    public function getCommunityCards($street)
    {
	if ($street == self::STREET_PREFLOP)
	    return array();

	if (! isset($this->community_cards[$street]))
	    return array();

	return $this->community_cards[$street];
    }

    public function createAction()
    { 
    	$action = new Action();
	$this->all_action[] = $action;

	return $action;
    }

    public function getPreflopAction()
    {
    	return getStreetAction(self::STREET_PREFLOP);
    }

    public function getFlopAction()
    {
    	return getStreetAction(self::STREET_FLOP);
    }

    public function getTurnAction()
    {
    	return getStreetAction(self::STREET_TURN);
    }

    public function getRiverAction()
    {
    	return getStreetAction(self::STREET_RIVER);
    }

    public function getAllAction()
    {
    	return $this->all_action;
    }

    public function getStreetAction($street)
    {
    	$street_action = array();

	foreach ($this->getAllAction() as $action)
	    if ($action->getStreet() == $street)
	    	$street_action[] = $action;

    	return $street_action;
    }

    private function getShowdownActionActions()
    {
        return array(Action::SHOWDOWN, Action::MUCK, Action::RESULT);
    }

    public function getShowdownAction()
    {
    	$has_showdown = false;
	$showdown_action = array();

	foreach ($this->getAllAction() as $action) {
	    if (in_array($action->getAction(), $this->getShowdownActionActions())) {
		if ($action->getAction() == Action::SHOWDOWN)
		    $has_showdown = true;
		$showdown_action[] = $action;
	    }
	}

	if (! $has_showdown)
	    return false;

	return $showdown_action;
    }

    public static function validatePotNumber($i)
    {
        if (! is_int($i) || $i < 0)
            throw new InvalidArgumentException(sprintf("Invalid pot number `%i'", $i));

        return true;
    }

    public function setPot($i, $chips)
    {
        $this->validatePotNumber($i);
        $this->validateChipsAmount($chips);

    	$this->pots[$i] = $chips;

	return $this;
    }

    public function setMainPot($chips)
    {
    	return $this->setPot(0, $chips);
    }

    public function setSidePot($i, $chips)
    {
    	return $this->setPot($i, $chips);
    }

    public function getAllPots()
    {
    	return $this->pots;
    }

    public function getPot($i)
    {
        $pots = $this->getAllPots();

    	return $pots[$i];
    }

    public function getMainPot()
    {
	return $this->getPot(0);
    }

    public function getSidePot($i)
    {
    	return $this->getPot($i);
    }

    public function getAllSidePots()
    {
    	return array_slice($this->getPots(), 1);
    }

    public function setRake($rake)
    {
        $this->validateChipsAmount($rake);

    	$this->rake = $rake;

	return $this;
    }

    public function getRake()
    {
	return $this->rake;
    }

    public function getBoard()
    {
    	return $this->getAllCommunityCards();
    }

    public function createSummary()
    {
    	$summary = new Summary();
	$this->summary_seats[] = $summary;

	return $summary;
    }

    public function getSummarySeats()
    {
	$result = array();

	foreach ($this->summary_seats as $seat)
	    $result[$seat->getSeat()] = $seat;

	ksort($result, SORT_NUMERIC);

	return $result;
    }
}

