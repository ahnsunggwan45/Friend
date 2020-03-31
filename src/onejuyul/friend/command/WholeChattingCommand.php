<?php
namespace onejuyul\friend\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use onejuyul\friend\Friend;

class WholeChattingCommand extends Command
{

    public function __construct()
    {
        parent::__construct("전체채팅", "친구채팅 모드를 비활성화시킵니다.", "/전체채팅");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if ($sender instanceof Player)
            if (isset(Friend::$friendChat[$sender->getName()])) {
                unset(Friend::$friendChat[$sender->getName()]);
                $sender->sendMessage("§l§b[알림] §r§7전체채팅을 시작합니다.");
            }
    }
}