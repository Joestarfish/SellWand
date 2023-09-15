<?php

declare(strict_types=1);

namespace Joestarfish\SellWand;

use DaPigGuy\libPiggyEconomy\libPiggyEconomy;
use pocketmine\event\Listener;
use pocketmine\inventory\transaction\InventoryTransaction;
use pocketmine\plugin\PluginBase;
use customiesdevs\customies\item\CustomiesItemFactory;
use DaPigGuy\libPiggyEconomy\providers\EconomyProvider;
use dktapps\pmforms\ModalForm;
use Joestarfish\SellWand\item\SellWand;
use pocketmine\block\tile\Container;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\form\Form;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\world\Position;

class Main extends PluginBase implements Listener {
	private static Config $config;
	private EconomyProvider $provider;
	private array $items_values;

	public function onEnable(): void {
		self::$config = $this->getConfig();

		if (!$this->isItemListValid() || !$this->areVirionsLoaded()) {
			$this->getServer()
				->getPluginManager()
				->disablePlugin($this);
			return;
		}

		libPiggyEconomy::init();
		$this->provider = libPiggyEconomy::getProvider(
			$this->getConfig()->get('economy'),
		);

		CustomiesItemFactory::getInstance()->registerItem(
			SellWand::class,
			'sell_wand:sell_wand',
			$this->getItemName(),
		);

		$this->getServer()
			->getPluginManager()
			->registerEvents($this, $this);
	}

	public function onInteract(PlayerInteractEvent $event) {
		if ($event->getAction() != $event::LEFT_CLICK_BLOCK) {
			return;
		}

		$item_id = $event->getItem()->getTypeId();
		if ($item_id != SELL_WAND_TYPE_ID) {
			return;
		}

		$position = $event->getBlock()->getPosition();
		$tile = $position->getWorld()->getTile($position);

		if (!$tile instanceof Container) {
			return;
		}

		$event->cancel();

		$player = $event->getPlayer();

		if ($this->isConfirmFormEnabled()) {
			$player->sendForm($this->getForm($tile, $position));
			return;
		}

		$this->sellInventoryContents($tile, $player, $position);
	}

	private function sellInventoryContents(
		Container $container,
		Player $player,
		Position $position,
	) {
		$inventory = $container->getInventory();
		$items = [];
		$total_price = 0;

		foreach ($inventory->getContents() as $slot => $item) {
			$price = $this->items_values[$item->getTypeId()] ?? null;

			if ($price === null) {
				continue;
			}

			$total_price += $price * $item->getCount();

			$items[] = $item;
			$inventory->setItem($slot, VanillaItems::AIR());
		}

		if (count($items) == 0) {
			$player->sendMessage($this->getNoItemsMessage());
			return;
		}

		$this->simulateTransaction($player, $container);

		$this->addMoneyToPlayer($player, $total_price, $position, $items);
	}

	private function addMoneyToPlayer(
		Player $player,
		float $total_price,
		Position $position,
		array $items_list,
	) {
		$this->provider->giveMoney($player, $total_price, function (
			bool $success,
		) use ($player, $total_price, $position, $items_list): void {
			if ($success) {
				$this->handleMoneyAdded($player, $total_price);
				return;
			}

			$this->handleMoneyNotAdded($player, $position, $items_list);
		});
	}

	private function handleMoneyAdded(Player $player, float $total_price) {
		if (!$player->isConnected()) {
			return;
		}

		$message = $this->getSuccessMessage();

		if (!is_string($message)) {
			return;
		}

		$player->sendMessage(
			str_replace(
				['{amont}', '{unit}'],
				[$total_price, $this->provider->getMonetaryUnit()],
				$message,
			),
		);
	}

	private function handleMoneyNotAdded(
		Player $player,
		Position $position,
		array $items_list,
	) {
		$connected = $player->isConnected();

		$tile = $position->getWorld()->getTile($position);

		if ($tile instanceof Container) {
			$drops = $tile->getInventory()->addItem(...$items_list);
			$this->simulateTransaction($player, $tile);
		} else {
			$drops = $items_list;
		}

		if ($this->isDropEnabled()) {
			if ($this->doDropAtPlayerFeet() && $connected) {
				$position = $player->getPosition();
			}
			$world = $position->getWorld();
			foreach ($drops as $item) {
				$world->dropItem($position, $item);
			}
		}

		if (!$connected || !is_string($message = $this->getFailureMessage())) {
			return;
		}

		$player->sendMessage($message);
	}

	private function getForm(Container $container, Position $position): Form {
		return new ModalForm(
			$this->getFormTitle(),
			$this->getFormContent(),
			function (Player $player, bool $choice) use (
				$container,
				$position,
			): void {
				if (!$choice) {
					return;
				}

				$this->sellInventoryContents($container, $player, $position);
			},
		);
	}

	private function isItemListValid(): bool {
		$sell = self::$config->get('sell', []);

		if (!is_array($sell)) {
			throw new \Exception('The value of "sell" must be an array');
		}

		foreach ($sell as $item_name => $price) {
			/** @var ?Item $item */
			$item = StringToItemParser::getInstance()->parse($item_name);

			if (!$item) {
				$this->getLogger()->alert("The item $item_name does not exist");
				return false;
			}

			if (isset($this->items_values[($id = $item->getTypeId())])) {
				$this->getLogger()->alert(
					"The item $item_name is declared more than once",
				);
				return false;
			}

			if (!is_numeric($price)) {
				$this->getLogger()->alert(
					"The price for the item $item_name must be numeric",
				);
				return false;
			}

			if ($price < 0) {
				$this->getLogger()->alert(
					"The price for the item $item_name must be greater or equal to 0",
				);
				return false;
			}

			$this->items_values[$id] = $price;
		}

		return true;
	}

	private function areVirionsLoaded(): bool {
		$are_virions_loaded = true;

		if (!class_exists(libPiggyEconomy::class)) {
			$are_virions_loaded = false;
		}

		if ($this->isConfirmFormEnabled() && !class_exists(ModalForm::class)) {
			$are_virions_loaded = false;
		}

		if (!$are_virions_loaded) {
			$this->getLogger()->alert(
				'Please download this plugin as a phar file from https://poggit.pmmp.io/p/SellWand',
			);
		}

		return $are_virions_loaded;
	}

	private function simulateTransaction(Player $player, Container $container) {
		$inventory = $container->getInventory();
		$transaction = new InventoryTransaction($player, [
			new SlotChangeAction(
				$inventory,
				0,
				$inventory->getItem(0),
				$inventory->getItem(0),
			),
		]);
		$ev = new InventoryTransactionEvent($transaction);
		$ev->call();
	}

	public static function getItemTexture(): string {
		return self::$config->getNested('item.texture', 'carrot_on_a_stick');
	}

	private function getItemName(): string {
		return self::$config->getNested('item.name', 'Sell Wand');
	}

	private function isConfirmFormEnabled(): bool {
		return (bool) self::$config->getNested(
			'behaviour.send-confirm-form',
			true,
		);
	}

	private function isDropEnabled(): bool {
		return (bool) self::$config->getNested(
			'behaviour.drop-if-not-fit',
			true,
		);
	}

	private function doDropAtPlayerFeet(): bool {
		return (bool) self::$config->getNested(
			'behaviour.drop-at-player-feet',
			true,
		);
	}

	private function getSuccessMessage() {
		return self::$config->getNested('messages.success');
	}

	private function getFailureMessage() {
		return self::$config->getNested('messages.failed');
	}

	private function getNoItemsMessage(): string {
		return (string) self::$config->getNested('messages.no-items');
	}

	private function getFormTitle() {
		return (string) self::$config->getNested(
			'messages.form-title',
			'Sell Wand',
		);
	}

	private function getFormContent() {
		// TODO: We could tell the player how much items and the total price
		return (string) self::$config->getNested(
			'messages.form-content',
			'Would you like to sell the contents of this container ?',
		);
	}
}
