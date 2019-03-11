<?php

class Ignition2Ps
{
    const PS_ACCOUNT_PREFIX = 'Ignition';

    const PS_TABLE_PREFIX = 'Ignition';

    const PS_HAND_PREFIX = '99999';

    // Ignition hand history directory (full path)
    protected $ignition_hh_dir;

    // Ignition hand history account directory (dirname)
    protected $ignition_account_dir;

    // Current Ignition hand history file (basename)
    protected $ignition_hh_file;

    // PokerStars hand history directory (full path)
    protected $ps_hh_dir;

    // PokerStars hand history writer object
    protected $ps_hh;

    public function __construct($ignition_hh_dir, $ps_hh_dir)
    {
        $this->ignition_hh_dir = $ignition_hh_dir;
        $this->ps_hh_dir = $ps_hh_dir;

        $this->ps_hh = new PsHh($ps_hh_dir);
    }

    public function getIgnitionHhDir()
    {
        return $this->ignition_hh_dir;
    }

    public function getIgnitionAccountDir()
    {
        return $this->ignition_account_dir;
    }

    public function getIgnitionAccountId()
    {
        return $this->getIgnitionAccountDir();
    }

    public function getIgnitionHhFile()
    {
        return $this->ignition_hh_file;
    }

    public function getPsHandId($hand_id)
    {
        return self::PS_HAND_PREFIX . $hand_id;
    }

    /**
     * Table name to use for generated PokerStars hand history
     */
    public function getPsTableName($table)
    {
        return self::PS_TABLE_PREFIX . $table;
    }

    /**
     * Account ID (player name) to use for generated PokerStars hand history
     */
    public function getPsAccountId()
    {
        return self::PS_ACCOUNT_PREFIX . $this->getIgnitionAccountId();
    }

    /**
     * Process entire Ignition hand history directory
     */
    public function processHh()
    {
        debug("Ignition Hand History: %s", $this->ignition_hh_dir);

        if (! is_dir($this->ignition_hh_dir) || ! is_readable($this->ignition_hh_dir))
            throw new Exception($this->ignition_hh_dir . " does not exist or is not a directory");

        if ($dh = opendir($this->ignition_hh_dir)) {
            while (($ignition_account_dir = readdir($dh)) !== false) {
                if (! is_dir($this->ignition_hh_dir . '/' . $ignition_account_dir))
                    continue;

                if (! preg_match('/^\d+$/', $ignition_account_dir))
                    continue;

                $this->processAccountDir($ignition_account_dir);
            }

            closedir($dh);
        }

        // Save all generated PokerStars hands into hand history files
        $this->ps_hh->process();
    }

    /**
     * Process Ignition hand history account directory
     */
    protected function processAccountDir($ignition_account_dir)
    {
        debug("    Account: %s", $ignition_account_dir);

        $this->ignition_account_dir = $ignition_account_dir;

        if ($dh = opendir($this->ignition_hh_dir . '/' . $ignition_account_dir)) {
            while (($ignition_hh_file = readdir($dh)) !== false) {
                $ignition_full_path = $this->ignition_hh_dir . '/' . $ignition_account_dir . '/' . $ignition_hh_file;

                if (! is_file($ignition_full_path) || ! is_readable($ignition_full_path))
                    continue;

                if (! preg_match('/^\d+$/', $ignition_account_dir))
                    continue;

                $this->processFile($ignition_hh_file);
            }

            closedir($dh);
        }
    }

    /**
     * Process individual Ignition hand history file
     */
    protected function processFile($ignition_hh_file)
    {
        deb("        %s", $ignition_hh_file);

        $this->ignition_hh_file = $ignition_hh_file;

        $ignition_file = new IgnitionHhFile($this);

        try {
            if (! $ignition_file->open()) { 
                // Not an Ignition HH file, ignore
                return;
            }

            $i = 1;
            while ($hand = $ignition_file->convertNextHand()) {
                $this->ps_hh->store($hand);
                $i++;
            }
            debug(" (%d hands)", $i);
        } catch (IgnitionHhFileException $e) {
            throw new Exception(sprintf("%s in file %s on line %d: %s", $e->getMessage(), $ignition_file->getIgnitionHhFileFullPath(), $ignition_file->getLineNo(), $ignition_file->getLine()));

        } catch (Ignition2PsHandException $e) {
            throw new Exception(sprintf("%s in file %s on line %d", $e->getMessage(), $ignition_file->getIgnitionHhFileFullPath(), $ignition_file->getHandLineNo()));

        } catch (PsHhException $e) {
            throw new Exception(sprintf("%s", $e->getMessage()));
        }
    }
}

