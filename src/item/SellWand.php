<?php

declare(strict_types=1);

namespace Joestarfish\SellWand\item;

use customiesdevs\customies\item\component\HandEquippedComponent;
use customiesdevs\customies\item\CreativeInventoryInfo;
use customiesdevs\customies\item\ItemComponents;
use customiesdevs\customies\item\ItemComponentsTrait;
use Joestarfish\SellWand\Main;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;

class SellWand extends Item implements ItemComponents {
	use ItemComponentsTrait;

	public function __construct(
		ItemIdentifier $identifier,
		string $name = 'Unknown',
	) {
		parent::__construct($identifier, $name);
		$this->initComponent(
			Main::getItemTexture(),
			new CreativeInventoryInfo(CreativeInventoryInfo::CATEGORY_ITEMS),
		);

		define('SELL_WAND_TYPE_ID', $this->getTypeId());
	}

	public function getMaxStackSize(): int {
		return 1;
	}
}
