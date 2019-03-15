<?php

class Hand extends Base
{
    private $id;

    private $format;

    private $tournament;

    private $table_id;

    private $timestamp;

    private $game;

    private $limit;

    private $table_size;

    private $currency;

    private $game_posts;

    private $dealer_seat;

    private $players;

    private $posts;

    private $community_cards;

    private $action;

    private $pots;

    private $rake;

    private $summary_seats;

    public function __construct()
    {
        foreach (Post::getAllTypes() as $type)
            if (! Post::isSingle($type))
                $this->posts[$type] = array();

	$this->players = array();

	$this->community_cards = array();
        foreach (Street::getAllPostflop() as $street)
            $this->community_cards[$street] = array();

	$this->action = array();

	$this->pots = array(0 => null);

	$this->summary_seats = array();
    }

    public static function validateId($id)
    {
        if (! (is_numeric($id) || is_string($id)) || ! strlen($id))
            throw new InvalidArgumentException(sprintf("Invalid hand ID value", $id));
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

    public function setGame($game)
    {
        Game::validate($game);

	$this->game = $game;

	return $this;
    }

    public function getGame()
    {
	return $this->game;
    }

    public function setLimit($limit)
    {
        Limit::validate($limit);

    	$this->limit = $limit;

	return $this;
    }

    public function getLimit()
    {
	return $this->limit;
    }

    public function setTableSize($table_size)
    {
        TableSize::validate($table_size);

	$this->table_size = $table_size;

	return $this;
    }

    public function getTableSize()
    {
	return $this->table_size;
    }

    public function setCurrency($currency)
    {
        Currency::validate($currency);

        $this->currency = $currency;

        return $this;
    }

    public function addPlayer($seat, $name, $chips, $is_hero = false)
    {
	$this->players[$name] = new Player($seat, $name, $chips, $is_hero);

	return $this;
    }

    public function getPlayers()
    {
    	return $this->players;
    }

    public function getPlayer($name)
    {
        Player::validateName($name);

        foreach ($this->getPlayers() as $player)
            if ($player->getName() == $name)
                return $player;

        return false;
    }

    public function setPlayerCards($name, $cards)
    {
        $this->getPlayer($name)->setCards($cards);

	return $this;
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
        Player::validateSeat($i);

    	$seats = $this->getSeats();
	if (! isset($seats[$i]))
	    return false;

    	return $seats[$i];
    }

    public function setDealerSeat($dealer_seat)
    {
        Player::validateSeat($dealer_seat);

    	$this->dealer_seat = $dealer_seat;

	return $this;
    }

    public function getDealerSeat()
    {
	return $this->dealer_seat;
    }

    public function setGamePost($type, $chips)
    {
        $game_post = new GamePost($type, $chips);

        $this->game_posts[$type] = $game_post;

	return $this;
    }

    public function getGamePosts()
    {
    	return $this->game_posts;
    }

    public function getGamePost($type)
    {
        GamePost::validate($type);

        $posts = $this->getGamePosts();

    	return $posts[$type];
    }

    public function addPost($type, $chips, $player)
    {
	$post = new Post($type, $chips, $player);

	if (Post::isSingle($type))
	    $this->posts[$type] = $post;
	else
	    $this->posts[$type][] = $post;

	return $this;
    }

    public function getPosts()
    {
    	return $this->posts;
    }

    public function getPost($type)
    {
        Post::validate($type);

        $posts = $this->getPosts();

    	return $posts[$type];
    }

    public static function validateCard($card)
    {
        if (empty($card))
            throw new InvalidArgumentException("Empty card string");

        if (! preg_match('/^[2-9TJQKA][cdhs]$/', $card))
            throw new InvalidArgumentException(sprintf("Invalid card string `%s'", $card));
    }

    public function setCommunityCards($street, $cards)
    {
        if (is_string($cards))
            $cards = explode(' ', $cards);

        Street::validatePostflop($street);
        Street::validateCardCount($street, count($cards));

        foreach ($cards as $c)
            $this->validateCard($c);

    	$this->community_cards[$street] = $cards;

	return $this;
    }

    public function getCommunityCards($street = null)
    {
        if (! is_null($street)) {
            Street::validatePostflop($street);

            return $this->community_cards[$street];
        }

        $result = array();

        foreach ($this->community_cards as $street)
            $result = array_merge($result, $street);

        return $result;
    }

    public function addAction($street, $name, $type, ...$extra)
    { 
	$this->action[] = new Action($street, $name, $type);

	return $this;
    }

    public function getAction($street = null)
    {
        if (is_null($street))
            return $this->action;

        Street::validate($street);

    	$result = array();

	foreach ($this->action as $action) {
	    if ($action->getStreet() == $street)
	    	$result[] = $action;
        }

    	return $result;
    }

    public function getShowdownAction()
    {
    	$has_showdown = false;
	$result = array();

	foreach ($this->getAction() as $action) {
	    if (in_array($action->getType(), Action::getShowdownTypes())) {
		if ($action->getType() == Action::SHOWDOWN)
		    $has_showdown = true;
		$result[] = $action;
	    }
	}

	if (! $has_showdown)
	    return array();

	return $result;
    }

    public function addMainPot($chips)
    {
        if (isset($this->pots[0]))
            throw new LogicException("Multiple main pots not allowed");

        Chips::validate($chips);

        $this->pots[0] = new Pot(0, $chips);
    }

    public function addSidePot($chips)
    {
        $number = count($this->pots);

        $this->pots[$number] = new Pot($number, $chips);
    }

    public function getPots()
    {
        return $this->pots;
    }

    public function getMainPot()
    {
        return $this->pots[0];
    }

    public function getSidePots()
    {
    	return array_slice($this->getPots, 1);
    }

    public function setRake($rake)
    {
        Chips::validate($rake);

    	$this->rake = $rake;

	return $this;
    }

    public function getRake()
    {
	return $this->rake;
    }

    public function getBoard()
    {
    	return $this->getCommunityCards();
    }

    public function addSummary()
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

