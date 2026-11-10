## Themes

This module set provides users the ability to select from different UI themes.
A theme in Cypht is just a simple CSS file that overrides the default layout.

### Built-in themes

The themes listed in `hm_themes()` (`modules/themes/modules.php`) are loaded from
`modules/themes/assets/<name>/css/<name>.css`.

### Adding a custom theme

1. Create the CSS file `modules/themes/assets/<name>/css/<name>.css`.
   It usually starts by importing Bootstrap, then overrides what is needed:

   ```css
   @import url('../../../../../vendor/twbs/bootstrap/dist/css/bootstrap.min.css');

   body {
       background-color: #ffffff;
   }
   ```

2. Declare it in `config/themes.php`. One theme per line, in the form
   `<file>|<label>`, where `<file>` is the theme name (without the `.css`
   extension):

   ```php
   return [
       'theme' => [
           'mytheme|My Theme',
       ],
   ];
   ```

3. Regenerate the configuration (needed for the production `site/` build):

   ```sh
   php ./scripts/config_gen.php
   ```

4. Select the theme in **Settings → General → Theme**.

Notes:

- The theme name must match both the `assets/<name>/` directory and the
  `<name>.css` file name.
- `custom_themes()` accepts both `['mytheme|My Theme']` (documented format) and
  the historical `['theme' => ['mytheme|My Theme']]` form.
- Custom themes defined in `config/themes.php` are added to the built-in list
  and can be selected like any other theme.
