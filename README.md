***1. Clone the repo and install the Node Modules***

```
cd your_project_root_path
&& git clone https://github.com/studiochampgauche/wordpress-project-starter.git .
&& npm i
```

***2. In `src` directory, duplicate wp-config-sample.php to wp-config.php and setup it***

***3. On your project root, add a directory named `dist` and put inner the WordPress production files***

***4. If is your first time setup, run the command `gulp prod or gulp prod-watch`. If isn't, just run `gulp` command for continue watching.***

***5. Activate required `ACF PRO` and `Champ Gauche Helper` plugins***

___

When you add plugins, fonts, images, external css, external scss and external js, you need to rerun the `gulp prod or gulp prod-watch` command.

#### Why?
For performance, we don't watch in continue some directories considered like massive (or potentially massive in the future).

#### Affected directories:
- `src > extensions`
- `src > fonts`
- `src > images`
- `src > scss > inc`
- `src > js > inc`
___