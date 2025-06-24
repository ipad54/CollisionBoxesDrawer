<?php

namespace ipad54\collisionboxesdrawer\command;

use ipad54\collisionboxesdrawer\CollisionBoxDrawer;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;

final class BoxDrawerStickCommand extends Command implements PluginOwned{

	public function __construct(private readonly CollisionBoxDrawer $plugin){
		parent::__construct("boxdrawerstick", "Gives player a stick to render collision boxes", aliases: ["boxstick"]);

		$this->setPermission("collisionboxdrawer.command.boxdrawerstick");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		if(!$sender instanceof Player){
			$sender->sendMessage("Please run this command in-game.");
			return false;
		}
		$stick = VanillaItems::STICK()
			->addEnchantment(new EnchantmentInstance(VanillaEnchantments::VANISHING(), 1))
			->setCustomName("Collision boxes drawer stick");
		$stick->getNamedTag()->setByte(CollisionBoxDrawer::COLLISION_DRAWER_STICK_TAG, 1);
		$sender->getInventory()->addItem($stick);

		$sender->sendMessage("You have given yourself a collision boxer drawer stick.");
		return true;
	}

	public function getOwningPlugin() : Plugin{
		return $this->plugin;
	}
}
