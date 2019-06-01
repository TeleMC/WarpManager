<?php
namespace WarpManager;

use HotbarSystemManager\HotbarSystemManager;
use pocketmine\command\{Command, CommandSender};
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;
use TeleMoney\TeleMoney;
use UiLibrary\UiLibrary;

class WarpManager extends PluginBase {
    //public $pre = "§e§l[ §f시스템 §e]§r§e";
    private static $instance = null;
    public $pre = "§e•";

    public static function getInstance() {
        return self::$instance;
    }

    public function onLoad() {
        self::$instance = $this;
    }

    public function onEnable() {
        @mkdir($this->getDataFolder());
        $this->saveResource("Warps.yml");
        $this->warps = new Config($this->getDataFolder() . "Warps.yml", Config::YAML);
        $this->wdata = $this->warps->getAll();
        $this->user = new Config($this->getDataFolder() . "user.yml", Config::YAML);
        $this->udata = $this->user->getAll();
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        $this->money = TeleMoney::getInstance();
        $this->hotbar = HotbarSystemManager::getInstance();
        $this->ui = UiLibrary::getInstance();
    }

    public function onDisable() {
        $this->save();
    }

    public function save() {
        $this->warps->setAll($this->wdata);
        $this->warps->save();
        $this->user->setAll($this->udata);
        $this->user->save();
    }

    public function isRegisterWarp(string $name, string $warp) {
        return isset($this->udata[mb_strtolower($name)][$warp]);
    }

    public function registerWarp(string $name, string $warp) {
        $this->udata[mb_strtolower($name)][$warp] = true;
    }

    public function getWarps() {
        return $this->wdata;
    }

    public function WarpUI(Player $player) {
        $form = $this->ui->SimpleForm(function (Player $player, array $data) {
            if (!isset($data[0])) return false;
            $warp = $this->list[$player->getName()];
            unset($this->list[$player->getName()]);
            if (!isset($warp[$data[0]])) return false;
            $this->check($player, $warp[$data[0]]);
        });
        $form->setTitle("Tele Warp");
        $form->setContent($this->getWarningMessage());
        $count = 0;
        foreach ($this->getRegisteredWarps($player->getName()) as $warp => $value) {
            $this->list[$player->getName()][$count] = $warp;
            $form->addButton("§l" . $warp . "\n§r§8" . $warp . "(으)로 이동합니다. 이용료: " . $this->getPrice($player, $warp) . "테나");
            $count++;
        }
        $form->sendToPlayer($player);
    }

    private function check(Player $player, string $warp) {
        $price = $this->getPrice($player, $warp);
        $this->list_1[$player->getName()]["워프"] = $warp;
        $this->list_1[$player->getName()]["가격"] = $price;
        $form = $this->ui->ModalForm(function (Player $player, array $data) {
            $warp = $this->list_1[$player->getName()]["워프"];
            $price = $this->list_1[$player->getName()]["가격"];
            unset($this->list_1[$player->getName()]);
            if ($data[0] == true) {
                if ($this->Warp($player, $warp, $price)) {
                    $player->sendMessage("{$this->pre} {$warp}에 도착했습니다!");
                    $player->sendMessage("{$this->pre} 이용료는 {$price}테나 입니다.");
                } else {
                    $player->sendMessage("{$this->pre} 테나가 부족합니다..");
                }
            } else {
                $this->WarpUI($player);
            }
        });
        $form->setTitle("Tele Warp");
        $form->setContent("\n§c§l▶ §f정말 {$warp}(으)로 이동하시겠습니까?\n  이용료는 {$price}테나 입니다.");
        $form->setButton1("§l§8[예]");
        $form->setButton2("§l§8[아니오]");
        $form->sendToPlayer($player);
    }

    public function getPrice(Player $player, string $warp) {
        $pos = explode(":", $this->wdata[$warp]);
        $level = $player->getServer()->getLevelByName($pos[3]);
        $pos = new Vector3($pos[0], $pos[1], $pos[2]);
        $distance = $player->distance($pos);
        if ($player->getLevel()->getName() !== $player->getLevel()->getName())
            return 20000;
        else
            return (int) round($distance * 3.3);
    }

    public function Warp(Player $player, string $place, int $amount = 0) {
        if ($this->hotbar->sdata["유저워프"] == "금지") {
            if (!$player->isOp()) return false;
        }
        if ($amount !== 0) {
            if ($this->money->getMoney($player->getName()) < $amount) return false;
            $this->money->reduceMoney($player->getName(), $amount);
        }
        $warpData = explode(":", $this->wdata[$place]);
        $warp = new Position((float) $warpData[0], (float) $warpData[1], (float) $warpData[2], $this->getServer()->getLevelByName($warpData[3]));
        $player->teleport($warp, $player->getYaw(), $player->getPitch());
        return true;
    }

    private function getWarningMessage() {
        $text = "";
        $text .= "§c§l▶ §r§f이동 가능한 마을:\n  한번 이상 가봤던 마을만 이동 가능합니다.\n";
        $text .= "\n";
        $text .= "§a§l▶ §r§f어디로 이동 하시겠습니까?";
        return $text;
    }

    public function getRegisteredWarps(string $name) {
        return $this->udata[mb_strtolower($name)];
    }

    public function onCommand(CommandSender $sender, Command $cmd, $label, $args): bool {
        if ($cmd->getName() == "워프") {
            if (!$sender->isOp())
                return false;
            else {
                if (!isset($args[0])) {
                    $sender->sendMessage("{$this->pre} /워프 생성 <워프명> | 현재 위치에 워프를 생성합니다.");
                    $sender->sendMessage("{$this->pre} /워프 제거 <워프명> | 워프를 제거합니다.");
                    $sender->sendMessage("{$this->pre} /워프 목록 | 워프목록을 확인합니다.");
                } else {
                    switch ($args[0]) {
                        case "생성":
                            if (!isset($args[1])) {
                                $sender->sendMessage("{$this->pre} 워프명이 기입되지 않았습니다.");
                                return false;
                            }
                            unset($args[0]);
                            $place = implode(" ", $args);
                            $this->addWarp($place, $sender->x, $sender->y, $sender->z, $sender->getLevel());
                            $sender->sendMessage("{$this->pre} 성공적으로 [ §f{$place} §r§e] 워프를 생성하였습니다.");
                            break;

                        case "제거":
                            if (!isset($args[1])) {
                                $sender->sendMessage("{$this->pre} 워프명이 기입되지 않았습니다.");
                                return false;
                            }
                            unset($args[0]);
                            $place = implode(" ", $args);
                            if (!isset($this->wdata[$place])) {
                                $sender->sendMessage("{$this->pre} 해당 워프는 존재하지 않습니다.");
                                return false;
                            }
                            $this->removeWarp($place);
                            $sender->sendMessage("{$this->pre} 성공적으로 [ §f{$place} §r§e] 워프를 제거하였습니다.");
                            break;

                        default:
                            $sender->sendMessage("{$this->pre} /워프 생성 <워프명> | 현재 위치에 워프를 생성합니다.");
                            $sender->sendMessage("{$this->pre} /워프 제거 <워프명> | 워프를 제거합니다.");
                            $sender->sendMessage("{$this->pre} /워프 목록 | 워프목록을 확인합니다.");
                            break;
                    }
                    return true;
                }
                return false;
            }
            return false;
        }
        return false;
    }

    public function addWarp(string $place, float $x, float $y, float $z, string $world) {
        if (isset($this->wdata[$place])) return;
        $this->wdata[$place] = $x . ":" . $y . ":" . $z . ":" . $world;
    }

    public function removeWarp(string $place) {
        if (!isset($this->wdata[$place])) return;
        unset($this->wdata[$place]);
    }
}
