mjml-theme-converter
====================

A Symfony project created on March 20, 2019, 6:54 pm.

This application is used to convert MJML mail themes for PrestaShop into Twig
mail themes.

To convert a theme use this command:

```
./bin/console prestashop:mail:convert-mjml modern_mjml {twig_theme_path}
```

On Windows use

```
php bin/console prestashop:mail:convert-mjml modern_mjml {twig_theme_path}
```

`twig_theme_path` is the output folder (it must exist before running the command). It is an absolute path or a path
relative to the project root.

The `modern_mjml` theme is located in this project in the `mails/themes` folder.

Create your own email template
------------------------------

- fork `PrestaShop/mjml-theme-converter`, install it with `git clone`.
- Run `composer install`
- Install `mjml` (https://github.com/mjmlio/mjml). Either install it globally or install it with `npm install`.
- Copy `app/config/parameters.yml.dist` to `app/config/parameters.yml`. Set the parameter `mjml_use_npm` accordingly to
  the way mjml is installed
- The base theme, written in MJML (MailJet Markup Language) is in the folder `/mails/themes/modern_mjml`. Adjust it to
  your needs.
- Run above command (e.g. `php /bin/console prestashop:mail:convert-mjml modern_mjml ../prestashop/mails/themes/modern`
  if the two projects `PrestaShop/mjml-theme-converter` and `PrestaShop/Prestashop` are located in the same folder)
  to convert your mjml templates to twig templates. This will create all the twig files (and overwrite the existing).
- In Prestashop Backoffice, menu `Design - Email Theme`, select your theme and click on `Generate emails` for all your
  installed languages.


Note about the structure of the layout templates
------------------------------------------------

In `layout.mjml.twig` (and similar in `order_layout.mjml.twig`) we have:

    <mj-wrapper>
      {% block header %}
      {% include '@MjmlMailThemes/modern_mjml/components/header.mjml.twig' %}
      {% endblock %}

      {% block content %}
      {% endblock %}

      {% block footer_content %}
      {% endblock %}
    </mj-wrapper>
    {% block footer %}
      {% include '@MjmlMailThemes/modern_mjml/components/footer.mjml.twig' %}
    {% endblock %}

All blocks are wrapped by `<mj-wrapper>`, but the `footer`. If you want to change the wrapping,
in `src/AppBundle/Converter/TwigTemplateConverter.php`, you have to adapt the code such that `true` or `false` is passed
as parameter `$isWrapped` to reflect your changes.
