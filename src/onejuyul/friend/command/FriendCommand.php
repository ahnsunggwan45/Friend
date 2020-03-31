<?php
namespace onejuyul\friend\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use onejuyul\friend\Friend;

class FriendCommand extends Command
{

    public function __construct()
    {
        parent::__construct("친구");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if ($sender instanceof Player) {
            Friend::sendBaseFriendUI($sender);
        } else {
            $sender->sendMessage("인게임에서 실행해주세요.");
        }
    }
}