<?php

class IcHhReader extends Base implements HhReaderInterface
{
    protected $root_dirname;

    protected $parser;

    public function __construct(string $root_dirname)
    {
        $this->root_dirname = $root_dirname;
        $this->parser = new IcParser();
    }

    public function getAccounts()
    {
	$accounts = array();

        if ($dh = opendir($this->getRootDirname())) {
            while (($account_dirname = readdir($dh)) !== false) {
		$account_dirname_full = $this->getRootDirname() . '/' . $account_dirname;

                if (! is_dir($account_dirname_full))
                    continue;

                if (! preg_match('/^\d+$/', $account_dirname))
                    continue;

		$accounts[] = $account_dirname;
            }
	}

	closedir($dh);

	return $accounts;
    }

    protected function getParser()
    {
        $this->parser = $parser;
    }

    public function parseAccountHandHistory(string $account)
    {
	$hands = array();
	$account_dirname_full = $this->getRootDirname() . '/' . $account;

        if ($dh = opendir($account_dirname_full)) {
            while (($filename = readdir($dh)) !== false) {
		$filename_full = $account_dirname_full . '/' . $filename;

                if (! is_file($filename) || ! is_readable($filename))
                    continue;

		$REGEX_FILENAME = '/^HH(?<year>\d{4})(?<month>\d{2})(?<day>\d{2})-(?<hour>\d{2})(?<minute>\d{2})(?<second>\d{2}) - (?<id>\d+) - (?<format>[A-Z]+) - \$(?<sb>[0-9.]+)-\$(?<bb>[0-9.]+) - (?<game>[A-Z]+) - (?<limit>[A-Z]+) - TBL No.(?<table>\d+)\.txt$/';
		if (! preg_match($REGEX_FILENAME, $filename))
		    continue;

		$this->getParser()->setFilename($filename);

		while ($hand = $this->getParser()->parseNextHand()) {
		    $hands[] = $hand;
		}
            }
	}

	closedir($dh);

	return $hands;
    }
}

