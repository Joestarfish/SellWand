# SellWand

A PMMP plugin that add an item which can sell the contents of containers (Chest, Hopper, Furnace, etc.)

[![](https://poggit.pmmp.io/shield.api/SellWand)](https://poggit.pmmp.io/p/SellWand)
[![](https://poggit.pmmp.io/shield.dl.total/SellWand)](https://poggit.pmmp.io/p/SellWand)

# Usage

To get started, install the [Customies](https://poggit.pmmp.io/p/Customies/) plugin to your `plugins` folder

Then, start your server and give yourself a Sell Wand using the command `/give <name> sell_wand:sell_wand`

# Configuration

Inside of the `plugin_data/SellWand/config.yml` file, you may change the following:

-   **item** - Item configuration

    -   **texture** - The texture of the item
    -   **name** - The name of the Item

-   **economy** - This is used to determine which economy system to use

    -   **provider** - The economy provider: `economyapi | bedrockeconomy | xp`

-   **behaviour** - The behaviour of the Sell Wand

    -   **send-confirm-form** - Do we send a Form to the user asking them for confirmation
    -   **drop-if-not-fit** - Do we drop the items on the ground if they doesn't fit in their container (or if it is destroyed)
    -   **drop-at-player-feet** - Do we drop the items at the player feet instead of at the container location

-   **messages** - These are messages that will be sent to the player depending of the outcomes of the transaction

    -   **success** - The message sent when items are sold successfully
    -   **failed** - The message sent when the items weren't
    -   **no-items** - The message sent when there are no items in the container
    -   **form-title** - The title of the confirmation Form
    -   **form-content** - The content of the confirmation Form

-   **sell** - A list of items that can be sold. Use the format `<item_name>: <price>`
