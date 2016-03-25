<?php

namespace eclipse\EChopper;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\Listener;
use pocketmine\block\Block;
use pocketmine\Player;
use pocketmine\math\Vector3;
use pocketmine\item\Item;

class EChopper extends PluginBase implements Listener {

    const CONFIG_WOOD = "WoodIds";
    const CONFIG_LEAF = "LeafIds";
    const CONFIG_AXE = "AxeIds";
    const CONFIG_CHOPTYPE = "chopType";
    const CONFIG_NEWSAPPLING = "newSaplingAmount";
    const CONFIG_FORRESTTHICKNESS = "maxForestThickness";
    const BLOCKSIDE = 1;

    private $currentChopps = array();

    public function onLoad() {

    }

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveDefaultConfig();
        $this->reloadConfig();
    }

    public function onDisable() {

    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
        switch ($command->getName()) {
            default:
                return false;
        }
    }

    public function onBlockBreak(BlockBreakEvent $event) {
        $block = $event->getBlock();
        $player = $event->getPlayer();

        if (!$this->isPlayerChopping($player) && $this->isPartOfATree($block) && $this->canChopTree($player)) {
            $treeType = $block->getDamage();
            $this->chopDownTree($player, $block);
            $this->plantSaplingNearby($block, $treeType);
        }
    }

    public function isPartOfATree(Block $block) {
        $wood = $this->getConfig()->get(self::CONFIG_WOOD);
        $leaves = $this->getConfig()->get(self::CONFIG_LEAF);

        if (in_array($block->getID(), $wood)) {

            $currentBlock = $block;

            while (in_array($currentBlock->getID(), $wood)) {
                $currentBlock = $currentBlock->getSide(self::BLOCKSIDE);
            }

            $roofBlock = $currentBlock;

            if (in_array($roofBlock->getID(), $leaves)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function canChopTree(Player $player) {
        $tool = $player->getInventory()->getItemInHand();

        if (in_array($tool->getID(), $this->getConfig()->get(self::CONFIG_AXE))) {
            return true;
        } else {
            return false;
        }
    }

    public function isPlayerChopping(Player $player) {
        return isset($this->currentChopps[$player->getName()]);
    }

    public function chopDownTree(Player $player, Block $startBlock) {
        $this->currentChopps[$player->getName()] = true;
        $wood = $this->getConfig()->get(self::CONFIG_WOOD);
        $leaves = $this->getConfig()->get(self::CONFIG_LEAF);

        $currentBlock = $startBlock;

        while (in_array($currentBlock->getID(), $wood)) {
            $item = $player->getInventory()->getItemInHand();
            $this->level_useBreakOn($player->getLevel(), $currentBlock, $item, $player);
            $currentBlock = $currentBlock->getSide(self::BLOCKSIDE);
        }

        $roofBlock = $currentBlock;
        $item = $player->getInventory()->getItemInHand();
        $this->level_useBreakOn($player->getLevel(), $roofBlock, $item, $player);
        unset($this->currentChopps[$player->getName()]);
    }

    public function level_useBreakOn(\pocketmine\level\Level $level_Level, Vector3 $vector, Item &$item = null, Player $player = null) {

        $target = $level_Level->getBlock($vector);
        if ($item === null) {
            $item = new Air();
        }

        if ($player instanceof Player) {
            $ev = new BlockBreakEvent($player, $target, $item, ($player->getGamemode() & 0x01) === 1 ? true : false);

            if ($item instanceof Item and ! $target->isBreakable($item) and $ev->getInstaBreak() === false) {
                $ev->setCancelled();
            }
            if (!$player->isOp() and ( $distance = $level_Level->server->getConfigInt("spawn-protection", 16)) > -1) {
                $t = new Vector2($target->x, $target->z);
                $s = new Vector2($level_Level->getSpawn()->x, $level_Level->getSpawn()->z);
                if ($t->distance($s) <= $distance) {
                    $ev->setCancelled();
                }
            }
            $this->getServer()->getPluginManager()->callEvent($ev);
            if ($ev->isCancelled()) {
                return false;
            }

            $player->lastBreak = microtime(true);
        } elseif ($item instanceof Item and ! $target->isBreakable($item)) {
            return false;
        }

        $level = $target->getLevel();

        if ($level instanceof Level) {
            $above = $level->getBlock(new Vector3($target->x, $target->y + 1, $target->z));
            if ($above instanceof Block) {
                if ($above->getID() === Item::FIRE) {
                    $level->setBlock($above, new Air(), true, false, true);
                }
            }
        }
        $drops = $target->getDrops($item);
        $target->onBreak($item);
        if ($item instanceof Item) {
            $item->useOn($target);
            if ($item->isTool() and $item->getDamage() >= $item->getMaxDurability()) {
                $item = Item::get(Item::AIR, 0, 0);
            }
        }

        if (!($player instanceof Player) or ( $player->getGamemode() & 0x01) === 0) {
            foreach ($drops as $drop) {
                if ($drop[2] > 0) {
                    $level_Level->dropItem($vector->add(0.5, 0.5, 0.5), Item::get($drop[0], $drop[1], $drop[2]));
                }
            }
        }

        return true;
    }

    public function plantSaplingNearby(Block $location, $treeType) {

        $block = $location->getLevel()->getBlock($location);

        $underBlock = $block->getSide(0);

        if ($block->getID() == Block::AIR && ($underBlock->getID() == Block::DIRT || $underBlock->getID() == Block::GRASS)) {
            $location->getLevel()->setBlock($location, new \pocketmine\block\Sapling($treeType));
        }
    }

}
