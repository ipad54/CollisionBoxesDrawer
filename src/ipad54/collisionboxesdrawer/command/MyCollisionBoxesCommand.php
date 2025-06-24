<?php

namespace ipad54\collisionboxesdrawer\command;

use ipad54\collisionboxesdrawer\CollisionBoxDrawer;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;

final class MyCollisionBoxesCommand extends Command implements PluginOwned{

	public function __construct(private readonly CollisionBoxDrawer $plugin){
		parent::__construct("mycollisionboxes", "Renders executor's collision boxes", aliases: ["myboxes"]);

		$this->setPermission("collisionboxdrawer.command.mycollisionboxes");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		if(!$sender instanceof Player){
			$sender->sendMessage("Please run this command in-game.");
			return false;
		}
		$this->plugin->drawEntityCollisionBoxes($sender, $sender);
		return true;
	}

	public function getOwningPlugin() : Plugin{
		return $this->plugin;
	}
}
