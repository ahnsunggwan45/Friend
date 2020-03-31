<?php

namespace onejuyul\friend;

use name\uimanager\element\DropDown;
use name\uimanager\event\ModalFormResponseEvent;
use ojy\area\generator\FlainGenerator;
use ojy\area\generator\IslandGenerator;
use ojy\area\generator\SkyLandGenerator;
use pocketmine\level\generator\GeneratorManager;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\Player;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\Server;
use name\uimanager\SimpleForm;
use name\uimanager\element\Button;
use name\uimanager\UIManager;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use name\uimanager\CustomForm;
use name\uimanager\element\Label;
use name\uimanager\element\Input;
use onejuyul\friend\command\FriendCommand;
use pocketmine\event\player\PlayerChatEvent;
use onejuyul\friend\command\WholeChattingCommand;
use pocketmine\event\player\PlayerQuitEvent;
use ssss\utils\SSSSUtils;

class Friend extends PluginBase implements Listener
{

    public const FRIENDCHAT_UI_ID = 1867341;

    public const APPLY_UI_ID = 1867342;

    public const REJECT_UI_ID = 1867343;

    public const ACCEPT_UI_ID = 1867344;

    public const BASE_UI_ID = 1867345;

    /** @var array */
    public static $db = [];
    /** @var Config */
    public static $data;

    public static $friendChat = [];

    public function onEnable()
    {
        @mkdir($this->getDataFolder());
        self::$data = new Config($this->getDataFolder() . 'Friends.yml', Config::YAML, [
            'data' => []
        ]);
        self::$db = self::$data->getAll();
        $this->getServer()
            ->getPluginManager()
            ->registerEvents($this, $this);
        $this->getServer()
            ->getCommandMap()
            ->register('Friend', new FriendCommand());
        $this->getServer()
            ->getCommandMap()
            ->register('Friend', new WholeChattingCommand());
    }

    public function onJoin(PlayerJoinEvent $event)
    {
        $player = $event->getPlayer();
        if (!isset(self::$db['data'][$player->getName()])) {
            self::$db['data'][$player->getName()] = [
                'f-list' => [],
                'a-list' => []
            ];
        }
        $o = $this->getOnlineFriendList($event->getPlayer());
        foreach ($o as $p) {
            $p = Server::getInstance()->getPlayerExact($p);
            if ($p instanceof Player)
                $p->sendMessage("§l§a[친구] §r§7친구 \"§a{$event->getPlayer()->getName()}§7\" 님이 접속했습니다!");
        }
    }

    public function onQuit(PlayerQuitEvent $event)
    {
        $player = $event->getPlayer();
        $o = $this->getOnlineFriendList($player);
        foreach ($o as $p) {
            $p = Server::getInstance()->getPlayerExact($p);
            if ($p instanceof Player)
                $p->sendMessage("§l§a[친구] §r§7친구 \"§a{$event->getPlayer()->getName()}§7\" 님이 퇴장했습니다.");
        }
    }

    public static function reject(Player $receiver, string $senderName)
    {
        if (isset(self::$db['data'][$receiver->getName()]['a-list'][$senderName])) {

            unset(self::$db['data'][$receiver->getName()]['a-list'][$senderName]);
            $sender = Server::getInstance()->getPlayer($senderName);
            if ($sender !== null) {
                $sender->sendMessage("§l§b[알림] §r§a{$receiver->getName()}§7 님이 친구 신청을 거절하였습니다.");
            }
            $receiver->sendMessage("§l§b[알림] §r§a{$senderName}§7 님의 친구 신청을 거절하였습니다.");
        }
    }

    public static function removeFriend(Player $player, string $friendName): bool
    {
        if (isset(self::$db['data'][$player->getName()]['f-list'][$friendName])) {
            unset(self::$db['data'][$player->getName()]['f-list'][$friendName]);
            unset(self::$db['data'][$friendName]['f-list'][$player->getName()]);
            $friend = Server::getInstance()->getPlayerExact($friendName);
            if ($friend !== null)
                SSSSUtils::message($friend, "{$player->getName()}님이 당신을 친구목록에서 삭제했습니다.");
            SSSSUtils::message($player, "{$friendName}님을 친구목록에서 삭제했습니다.");
            return true;
        }
        return false;
    }

    public static function addFriend(Player $receiver, string $senderName)
    {
        if (isset(self::$db['data'][$receiver->getName()]['a-list'][$senderName])) {
            self::$db['data'][$senderName]['f-list'][$receiver->getName()] = date('Y-m-d');
            self::$db['data'][$receiver->getName()]['f-list'][$senderName] = date('Y-m-d');
            unset(self::$db['data'][$receiver->getName()]['a-list'][$senderName]);
            $sender = Server::getInstance()->getPlayer($senderName);
            if ($sender !== null) {
                $sender->sendMessage("§l§b[알림] §r§a{$receiver->getName()}§7 님이 친구 신청을 수락하였습니다.");
            }
            $receiver->sendMessage("§l§b[알림] §r§a{$senderName}§7 님의 친구 신청을 수락하였습니다.");
        }
    }

    public static function applyFriend(Player $sender, Player $receiver)
    {
        if (!isset(self::$db["data"][$receiver->getName()]["a-list"][$sender->getName()])) {
            if (!isset(self::$db["data"][$receiver->getName()]["f-list"][$sender->getName()])) {
                if (!isset(self::$db["data"][$sender->getName()]["a-list"][$sender->getName()])) {
                    self::$db["data"][$receiver->getName()]["a-list"][$sender->getName()] = true;
                    $sender->sendMessage("§l§b[알림] §r§a{$receiver->getName()}§7 님에게 친구 신청을 보냈습니다.");
                    $receiver->sendMessage("§l§b[알림] §r§a{$sender->getName()}§7 님으로부터 친구 신청이 왔습니다.");
                    $receiver->addTitle("§l§a[§f친구신청§a]", "§b* §a{$sender->getName()}§f 님의  친구신청.");
                } else {
                    self::addFriend($sender, $receiver->getName());
                }
            } else {
                $sender->sendMessage('§l§b[알림] §r§7이미 그 플레이어와 친구를 맺었습니다.');
            }
        } else {
            $sender->sendMessage('§l§b[알림] §r§7이미 그 플레이어에게 친구신청을 보냈습니다.');
        }
    }

    public function onChat(PlayerChatEvent $event)
    {
        $player = $event->getPlayer();
        if (isseT(self::$friendChat[$player->getName()])) {
            $receiverName = self::$friendChat[$player->getName()];

            $format = "§l§a[친구] §r§7{$player->getName()} -> {$receiverName} > {$event->getMessage()}";
            $event->setFormat($format);

            $receiverP = $this->getServer()->getPlayer($receiverName);
            if ($receiverP !== null) {
                $receiverP->sendMessage($format);
                $player->sendMessage($format);
            } else {
                $player->sendMessage("§l§b[알림] §r§a{$receiverName} §7님이 서버에서 나가셔서 채팅모드가 해제됩니다.");
                unset(self::$friendChat[$player->getName()]);
                return;
            }
            foreach ($this->getServer()->getOnlinePlayers() as $p) {
                if ($p->isOp() and ($p->getName() !== $player->getName() and $p->getName() !== $receiverName)) {
                    $p->sendMessage($format);
                }
            }
            $event->setCancelled();
        }
    }

    public const WARP_ID = 71823123;

    /** @var Player[] */
    public static $warpQueue = [];

    public static function sendFriendWarpUI(Player $player)
    {
        $onlineFriendsName = self::getOnlineFriendList($player);
        self::$warpQueue[$player->getName()] = $onlineFriendsName;
        /*$onlineFriendsName = array_map(function (Player $player) {
            return $player->getName();
        }, $onlineFriends);*/
        $form = new CustomForm('§l친구에게 워프');
        $form->addElement(new DropDown("§r§b• §f워프할 친구를 선택하세요!", $onlineFriendsName));

        UIManager::getInstance()->sendUI($player, $form, self::WARP_ID);
    }

    public const REMOVE_ID = 61617231;

    public static function sendRemoveFriendUI(Player $player)
    {
        $friendList = self::getFriendList($player);
        $form = new SimpleForm("§l친구 삭제", "\n\n§r§b• §f삭제할 친구를 선택하세요.\n");
        foreach ($friendList as $friendName) {
            $form->addButton(new Button("§l{$friendName}\n§r§8{$friendName} 님을 삭제합니다."));
        }
        $form->setHandler(function ($data) use ($player) {
            if ($data !== null) {
                $friendList = self::getFriendList($player);
                if (isset($friendList[$data])) {
                    $friendName = $friendList[$data];
                    if (!self::removeFriend($player, $friendName)) {
                        SSSSUtils::message($player, '알 수 없는 오류로 삭제하지 못했습니다.');
                    }
                }
            }
        });
        UIManager::getInstance()->sendUI($player, $form, self::REMOVE_ID);
    }

    public function receive(ModalFormResponseEvent $event)
    {
        $player = $event->getPlayer();
        $formId = $event->getFormId();

        $data = $event->getFormData();
        if ($data !== null) {
            if ($formId === self::REMOVE_ID) {
                $form = UIManager::getInstance()->getForm($player, $formId);
                if ($form !== null) {
                    $form->handle($data);
                    UIManager::getInstance()->unregisterForm($player, $formId);
                }
            } elseif ($formId === self::BASE_UI_ID) {
                if ($data === 0) {
                    self::sendAcceptUI($player);
                } elseif ($data === 1) {
                    self::sendRejectUI($player);
                } elseif ($data === 2) {
                    self::sendFriendListUI($player);
                } elseif ($data === 3) {
                    self::sendFriendApplyUI($player);
                } elseif ($data === 4) {
                    self::sendSelectFriendChatUI($player);
                } elseif ($data === 5) {
                    if (count(self::getOnlineFriendList($player)) > 0) {
                        self::sendFriendWarpUI($player);
                    } else {
                        $player->sendMessage('§l§b[알림] §r§7접속중인 친구가 없습니다.');
                    }
                } elseif ($data === 6) {
                    self::sendRemoveFriendUI($player);
                }
            } elseif ($formId === self::WARP_ID) {
                $warpQueue = self::$warpQueue[$player->getName()];
                if ($data !== null) {
                    if ($data[0] !== null) {
                        $friend = $warpQueue[$data[0]];
                        $friend = Server::getInstance()->getPlayerExact($friend);
                        if ($friend instanceof Player && $friend->isOnline()) {
                            $generator = GeneratorManager::getGenerator($friend->level->getProvider()->getGenerator());
                            if ($generator === IslandGenerator::class || $generator === SkyLandGenerator::class || $generator === FlainGenerator::class) {
                                $player->teleport($friend);
                                $player->sendMessage('§l§b[알림] §r§7친구에게로 텔레포트했습니다.');
                            } else {
                                $player->sendMessage('§l§b[알림] §r§7친구가 섬이나 평야에 위치하고있지 않습니다.');
                            }
                        } else {
                            $player->sendMessage('§l§b[알림] §r§7잘못된 요청입니다. 다시시도해주세요.');
                        }
                    }
                }
            } elseif ($formId === self::ACCEPT_UI_ID) {
                $senderName = array_keys(self::$db['data'][$player->getName()]['a-list'])[$data];
                self::addFriend($player, $senderName);
                self::sendAcceptUI($player);
            } elseif ($formId === self::REJECT_UI_ID) {
                $senderName = array_keys(self::$db['data'][$player->getName()]['a-list'])[$data];
                self::reject($player, $senderName);
                self::sendRejectUI($player);
            } elseif ($formId === self::APPLY_UI_ID) {
                if (isset($data[0])) {
                    $receiver = $data[0];
                    $receiverP = Server::getInstance()->getPlayer($receiver);
                    if ($receiverP !== null) {

                        self::applyFriend($player, $receiverP);
                    } else {
                        $player->sendMessage("§l§b[알림] §r§7\"§a{$receiver}§7\" 님은 현재 접속중이 아닙니다.");
                    }
                }
            } elseif ($formId === self::FRIENDCHAT_UI_ID) {
                $online = self::getOnlineFriendList($player);
                if (isset($online[$data])) {
                    $friendName = $online[$data];
                    $friendP = Server::getInstance()->getPlayer($friendName);
                    if ($friendP !== null)
                        $friendP->sendMessage("§l§b[알림] §r§a{$player->getName()} §7님이 당신에게 채팅을 시작했습니다.");
                    $player->sendMessage("§l§b[알림] §r§a{$friendName}§7 님에게 채팅을 보냅니다.");
                    $player->sendMessage('§l§b[알림] §r§7다시 전체채팅을 원할시 [ /전체채팅 ] 을 입력해주세요.');
                    self::$friendChat[$player->getName()] = $friendName;
                } else {
                    unset(self::$friendChat[$player->getName()]);
                    $player->sendMessage('§l§b[알림] §r§a친구채팅 모드를 해제했습니다.');
                }
            }
        }
    }


    /**
     * @param Player $player
     * @return string[]
     */
    public static function getOnlineFriendList(Player $player)
    {
        if (isset(self::$db['data'][$player->getName()])) {
            $list = array_keys(self::$db['data'][$player->getName()]['f-list']);
            $online = [];
            foreach ($list as $n) {
                $p = Server::getInstance()->getPlayer($n);
                if ($p !== null)
                    $online[] = $n;
            }
            return $online;
        }
        return [];
    }

    public static function sendSelectFriendChatUI(Player $player)
    {
        $list = array_keys(self::$db['data'][$player->getName()]['f-list']);
        $online = [];
        foreach ($list as $n) {
            $p = Server::getInstance()->getPlayer($n);
            if ($p !== null)
                $online[] = $n;
        }
        if (count($online) > 0) {
            $form = new SimpleForm('친구', "\n함께 채팅할 친구를 클릭하세요.\n");
            foreach ($online as $n) {
                $form->addButton(new Button("§l§6• §8{$n} 님과 채팅하기\n§r§8클릭시 친구채팅모드가 활성화됩니다."));
            }
            $form->addButton(new Button("§l§6• §8친구채팅 그만하기\n§r§8클릭시 친구채팅모드가 비활성화 됩니다."));
        } else {
            $form = new SimpleForm('친구', "\n§a온라인§f인 친구가 없습니다.\n");
        }
        UIManager::getInstance()->sendUI($player, $form, self::FRIENDCHAT_UI_ID);
    }

    public static function sendFriendApplyUI(Player $player)
    {
        $form = new CustomForm('친구');
        $form->addElement(new Input('친구 신청', '닉네임을 적어주세요.', ''));
        UIManager::getInstance()->sendUI($player, $form, self::APPLY_UI_ID);
    }

    public static function getFriendList(Player $player): array
    {
        if (isset(self::$db['data'][$player->getName()]['f-list']))
            return array_keys(self::$db['data'][$player->getName()]['f-list']);
        return [];
    }

    public static function sendFriendListUI(Player $player)
    {
        $list = array_keys(self::$db['data'][$player->getName()]['f-list']);
        if (count($list) > 0) {
            $online = [];
            $offline = [];
            foreach ($list as $n) {
                $p = Server::getInstance()->getPlayer($n);
                if ($p !== null)
                    $online[] = $n;
                else
                    $offline[] = $n;
            }

            $form = new CustomForm('친구');
            $form->addElement(new Label('§a* §f온라인 - ' . implode(', ', $online) . "\n\n§c* §f오프라인 - " . implode(", ", $offline)));
        } else {
            $form = new CustomForm('친구');
            $form->addElement(new Label('친구가 없습니다.'));
        }
        UIManager::getInstance()->sendUI($player, $form, 999999998);
    }

    public static function sendRejectUI(Player $player)
    {
        $list = array_keys(self::$db['data'][$player->getName()]['a-list']);
        if (count($list) > 0) {
            $form = new SimpleForm('친구', "\n§e• §f친구 신청을 거절합니다.\n");
            foreach ($list as $n) {
                $form->addButton(new Button("§l§6• §8{$n} 님의 신청\n§r§8클릭시 친구신청을 거절합니다."));
            }
        } else {
            $form = new SimpleForm('친구', "\n§e• §f친구 신청이 오지 않았습니다.\n");
        }
        UIManager::getInstance()->sendUI($player, $form, self::REJECT_UI_ID);
    }

    public static function sendAcceptUI(Player $player)
    {
        $list = array_keys(self::$db['data'][$player->getName()]['a-list']);
        if (count($list) > 0) {
            $form = new SimpleForm('친구', "\n§e• §f친구 신청을 거절합니다.\n");
            foreach ($list as $n) {
                $form->addButton(new Button("§l§6• §8{$n} 님의 신청\n§r§8클릭시 친구신청을 수락합니다."));
            }
        } else {
            $form = new SimpleForm('친구', "\n§e• §f친구 신청이 오지 않았습니다.\n");
        }
        UIManager::getInstance()->sendUI($player, $form, self::ACCEPT_UI_ID);
    }

    public static function sendBaseFriendUI(Player $player)
    {
        $aCount = count(self::$db['data'][$player->getName()]['a-list']);
        $list = implode(", ", array_keys(self::$db["data"][$player->getName()]['a-list']));
        $form = new SimpleForm('친구', "\n§e• §f친구 신청이 §a{$aCount}§f건 있습니다: {$list}\n");
        $form->addButton(new Button("§l수락\n§r§b* §8친구 신청을 수락합니다."));
        $form->addButton(new Button("§l거절\n§r§b* §8친구 신청을 거절합니다."));
        $form->addButton(new Button("§l목록\n§r§b* §8친구 목록을 확인합니다."));
        $form->addButton(new Button("§l신청\n§r§b* §8다른 플레이어에게 친구신청을 보냅니다."));
        $form->addButton(new Button("§l채팅\n§r§b* §8친구 전용 채팅모드를 활성화합니다."));
        $form->addButton(new Button("§l워프\n§r§b* §8친구가 위치한곳으로 워프합니다."));
        $form->addButton(new Button("§l삭제\n§r§b* §8친구를 친구목록에서 삭제합니다."));
        UIManager::getInstance()->sendUI($player, $form, SELF::BASE_UI_ID);
    }

    public static function save()
    {
        self::$data->setAll(self::$db);
        self::$data->save();
    }

    public function onDisable()
    {
        self::save();
    }
}