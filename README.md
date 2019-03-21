mjml-theme-converter
====================

A Symfony project created on March 20, 2019, 6:54 pm.

This application is used to convert MJML mail themes for PrestaShop into Twig
mail themes.

To convert a theme use this command:

```
./bin/console prestashop:mail:convert-mjml modern_mjml {twig_theme_path}
```

The `modern_mjml` theme is located in this project in the `mails/themes` folder.
