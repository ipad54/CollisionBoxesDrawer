<?php

namespace ipad54\collisionboxesdrawer;

use ipad54\collisionboxesdrawer\command\BoxDrawerStickCommand;
use ipad54\collisionboxesdrawer\command\MyCollisionBoxesCommand;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerEntityInteractEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\ServerScriptDebugDrawerPacket;
use pocketmine\network\mcpe\protocol\types\PacketShapeData;
use pocketmine\network\mcpe\protocol\types\ScriptDebugShapeType;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;

final class CollisionBoxDrawer extends PluginBase implements Listener{
	public const COLLISION_DRAWER_STICK_TAG = "ipad54:CollisionDrawerStick"; //TAG_Byte

	/** @var int[][][][][] */
	private array $drawingBlockBoxes = [];
	/** @var int[][][] */
	private array $drawingEntityBoxes = [];

	private int $nextNetworkShapeId = 0;

	protected function onEnable() : void{
		$map = $this->getServer()->getCommandMap();
		$map->register("collisionboxesdrawer", new BoxDrawerStickCommand($this));
		$map->register("collisionboxesdrawer", new MyCollisionBoxesCommand($this));

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() : void{
			foreach($this->drawingEntityBoxes as $name => $data){
				$player = $this->getServer()->getPlayerExact($name);
				if($player === null){
					unset($this->drawingEntityBoxes[$name]);
				}else{
					foreach($data as $entityId => $drawNetworkIds){
						$entity = $player->getWorld()->getEntity($entityId);
						if($entity === null || ($entity !== $player && !isset($entity->getViewers()[spl_object_id($player)]))){
							$this->hideCollisionBoxes($player, $drawNetworkIds);
							unset($this->drawingEntityBoxes[$name][$entityId]);
						}else{
							$this->drawingEntityBoxes[$name][$entityId] = $this->drawCollisionBoxes($player, [$entity->getBoundingBox()], $drawNetworkIds);
						}
					}
				}
			}
		}), 1);
	}

	public function handlePlayerInteract(PlayerInteractEvent $event) : void{
		if($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK && $event->getItem()->getNamedTag()->getByte(self::COLLISION_DRAWER_STICK_TAG, 0) === 1){
			$event->cancel();

			$player = $event->getPlayer();
			$name = $player->getName();
			$block = $event->getBlock();
			$pos = $block->getPosition();
			if(isset($this->drawingBlockBoxes[$name][$x = $pos->getX()][$y = $pos->getY()][$z = $pos->getZ()])){
				$this->hideCollisionBoxes($player, $this->drawingBlockBoxes[$name][$x][$y][$z]);
				unset($this->drawingBlockBoxes[$name][$x][$y][$z]);

				$player->sendMessage("You turned off collision boxes rendering for " . $block->getName());
			}else{
				$boxes = $block->getCollisionBoxes();
				if(count($boxes) > 0){
					$this->drawingBlockBoxes[$name][$x][$y][$z] = $this->drawCollisionBoxes($player, $boxes);
				}

				$brackets = count($boxes) !== 1;
				$boxesMessages = [];
				foreach($boxes as $bb){
					$bb = $bb->offsetCopy(-$x, -$y, -$z);
					$boxesMessages[] = sprintf("[minX=%g, minY=%g, minZ=%g, maxX=%g, maxY=%g, maxZ=%g]", $bb->minX, $bb->minY, $bb->minZ, $bb->maxX, $bb->maxY, $bb->maxZ);
				}
				$player->sendMessage($block->getName() . " collision boxes: " . ($brackets ? "[" : "") . implode(", ", $boxesMessages) . ($brackets ? "]" : ""));
			}
		}
	}

	public function handlePlayerEntityInteract(PlayerEntityInteractEvent $event) : void{
		if($event->getPlayer()->getInventory()->getItemInHand()->getNamedTag()->getByte(self::COLLISION_DRAWER_STICK_TAG, 0) === 1){
			$this->drawEntityCollisionBoxes($event->getPlayer(), $event->getEntity());
			$event->cancel();
		}
	}

	public function handlePlayerQuit(PlayerQuitEvent $event) : void{
		unset($this->drawingBlockBoxes[$event->getPlayer()->getName()]);
		unset($this->drawingEntityBoxes[$event->getPlayer()->getName()]);
	}

	public function drawEntityCollisionBoxes(Player $player, Entity $entity) : void{
		$name = $player->getName();
		$id = $entity->getId();

		if(isset($this->drawingEntityBoxes[$name][$id])){
			$this->hideCollisionBoxes($player, $this->drawingEntityBoxes[$name][$id]);
			unset($this->drawingEntityBoxes[$name][$id]);

			$player->sendMessage("You turned off collision boxes rendering for " . ($entity instanceof Living ? $entity->getName() : $entity::getNetworkTypeId()));
		}else{
			$bb = $entity->getBoundingBox();
			$this->drawingEntityBoxes[$name][$id] = $this->drawCollisionBoxes($player, [$bb]);

			$pos = $entity->getPosition();
			$bb = $bb->offsetCopy(-$pos->getX(), -$pos->getY(), -$pos->getZ());

			$player->sendMessage(($entity instanceof Living ? $entity->getName() : $entity::getNetworkTypeId()) . " collision box: " . sprintf("[minX=%g, minY=%g, minZ=%g, maxX=%g, maxY=%g, maxZ=%g]", $bb->minX, $bb->minY, $bb->minZ, $bb->maxX, $bb->maxY, $bb->maxZ));
		}
	}

	/**
	 * @param AxisAlignedBB[] $boxes
	 *
	 * @return int[]
	 */
	private function drawCollisionBoxes(Player $player, array $boxes, array $customNetworkIds = []) : array{
		$shapes = [];
		$networkIds = [];
		$count = 0;
		foreach($boxes as $box){
			$networkId = count($customNetworkIds) === 0 ? $this->nextNetworkShapeId++ : $customNetworkIds[$count];
			$networkIds[] = $networkId;
			$count++;

			$shapes[] = new PacketShapeData(
				$networkId,
				ScriptDebugShapeType::BOX,
				new Vector3($box->minX, $box->minY, $box->minZ),
				null,
				null,
				null,
				null,
				null,
				new Vector3($box->maxX - $box->minX, $box->maxY - $box->minY, $box->maxZ - $box->minZ),
				null,
				null,
				null,
				null
			);
		}
		$player->getNetworkSession()->sendDataPacket(ServerScriptDebugDrawerPacket::create($shapes));
		return $networkIds;
	}

	private function hideCollisionBoxes(Player $player, array $networkIds) : void{
		$player->getNetworkSession()->sendDataPacket(ServerScriptDebugDrawerPacket::create(
			array_map(fn(int $networkId) => new PacketShapeData($networkId, null, null, null, null, null, null, null, null, null, null, null, null),
                $networkIds
			)));
	}
}
