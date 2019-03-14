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

    const POST_SB = 'sb';
    const POST_BB = 'sb';
    const POST_OTHER = 'other';
    const POST_RETURN = 'return';

    protected $id;

    protected $table_id;

    protected $timestamp;

    protected $game;

    protected $limit;

    protected $table_size;

    protected $game_blinds;

    protected $dealer_seat;

    protected $posts;

    protected $players;

    protected $community_cards;

    protected $all_action;

    protected $pots;

    protected $rake;

    protected $summary_seats;

    public function __construct()
    {
	$this->players = array();

	$this->game_blinds = array();

	$this->posts = array(
	    'sb' => array(),
	    'bb' => array(),
	    'other' => array(),
	    'return' => array()
	);

	$this->community_cards = array(
	    self::STREET_FLOP => array(),
	    self::STREET_TURN => array(),
	    self::STREET_RIVER => array()
	);

	$this->action = array();

	$this->pots = array();

	$this->summary_seats = array();
    }

    public function setId($id)
    {
	$this->id = $id;

	return $this;
    }

    public function getId()
    {
	return $this->id;
    }

    public function setTableId($table_id)
    {
	$this->table_id = $table_id;

	return $this;
    }

    public function getTableId()
    {
	return $this->table_id;
    }

    public function setTimestamp($timestamp)
    {
	$this->timestamp = $timestamp;

	return $this;
    }

    public function getTimestamp()
    {
    	return $this->timestamp;
    }

    public function setGame($game)
    {
	$this->game = $game;

	return $this;
    }

    public function getGame()
    {
	return $this->game;
    }

    public function setLimit($limit)
    {
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

    public function setGameSb($sb)
    {
    	$this->game_blinds['sb'] = $sb;

	return $this;
    }

    public function getGameSb()
    {
	return $this->game_blinds['sb'];
    }

    public function setGameBb($bb)
    {
    	$this->game_blinds['bb'] = $bb;

	return $this;
    }

    public function getGameBb()
    {
	return $this->game_blinds['bb'];
    }

    public function setTableSize($table_size)
    {
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
    	$this->dealer_seat = $dealer_seat;

	return $this;
    }

    public function getDealerSeat()
    {
	return $this->dealer_seat;
    }

    public function addPost($type, $player, $chips)
    {
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

    public function getPostSb()
    {
    	return $this->posts[self::POST_SB];
    }

    public function getPostBb()
    {
    	return $this->posts[self::POST_BB];
    }

    public function getPostOther()
    {
    	return $this->posts[self::POST_OTHER];
    }

    public function getPostReturn()
    {
    	return $this->posts[self::POST_RETURN];
    }

    public function setCommunityCards($street, $cards)
    {
    	$this->community_cards[$street] = $cards;

	return $this;
    }

    public function setFlopCommunityCards($cards)
    {
    	$this->community_cards[self::STREET_FLOP] = $cards;

	return $this;
    }

    public function setTurnCommunityCards($cards)
    {
    	$this->community_cards[self::STREET_TURN] = $cards;

	return $this;
    }

    public function setRiverCommunityCards($cards)
    {
    	$this->community_cards[self::STREET_RIVER] = $cards;

	return $this;
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

    public function getShowdownAction()
    {
    	$has_showdown = false;
	$showdown_action = array();

	foreach ($this->getAllAction() as $action) {
	    if (in_array($action->getAction(), 
		    array(Action::SHOWDOWN, Action::MUCK, Action::RESULT))) {
		if ($action->getAction() == Action::SHOWDOWN)
		    $has_showdown = true;
		$showdown_action[] = $action;
	    }
	}

	if (! $has_showdown)
	    return false;

	return $showdown_action;
    }

    public function setPot($i, $chips)
    {
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
    	return $this->getAllPots()[$i];
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

