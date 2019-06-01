<?php
namespace WarpManager;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\Server;

class EventListener implements Listener {
    public function __construct(WarpManager $plugin) {
        $this->plugin = $plugin;
    }

    public function onJoin(PlayerJoinEvent $ev) {
        $player = $ev->getPlayer();
        if (!isset($this->plugin->udata[mb_strtolower($player->getName())])) {
            $this->plugin->udata[mb_strtolower($player->getName())] = [];
        }
    }

    public function onMove(PlayerMoveEvent $ev) {
        $player = $ev->getPlayer();
        if (!isset($this->time[$player->getName()]))
            $this->time[$player->getName()] = microtime(true);
        if (microtime(true) - $this->time[$player->getName()] <= 1)
            return false;
        $this->time[$player->getName()] = microtime(true);
        foreach ($this->plugin->getWarps() as $warp => $pos) {
            if ($this->plugin->isRegisterWarp($player->getName(), $warp))
                continue;
            $pos = explode(":", $pos);
            $level = $player->getServer()->getLevelByName($pos[3]);
            $pos = new Vector3($pos[0], $pos[1], $pos[2]);
            if ($player->getLevel()->getName() == $level->getName() && $player->distance($pos) <= 15) {
                $this->plugin->registerWarp($player->getName(), $warp);
            }
        }
    }
}
