# Demo imagery

The six catalog assets in this directory are original Agovena demo assets created for this repository. They contain no provider credentials, real endpoints or customer data, and seeding the demo needs no network download.

`php artisan agovena:seed-demo --force` copies these assets into `storage/app/public/demo/`. Normal application boot and standard database seeding do not load them.

The Minecraft demo cover contains the official Minecraft wordmark, sourced from the Minecraft usage guidelines: https://www.minecraft.net/en-us/usage-guidelines. It is used only for this clearly unofficial demo listing, which includes the required non-affiliation disclaimer. All rights remain with Mojang and Microsoft.
