# Pixel Review Member Pages Addon

Detta projekt är ett WordPress-tillägg som utökar funktionaliteten för Pixel Review genom att lägga till medlemssidor och relaterade funktioner.

## Funktioner
- Hantering av medlemssidor
- Anpassade kortkoder
- Administrationsinställningar
- Åtkomstbegränsningar för sidor
- Flash-meddelanden och användarinteraktioner

## Katalogstruktur
```
sh-review-member-pages.php         # Huvudpluginfil
assets/
  css/
    pr-member-pages.css           # CSS för medlemssidor
includes/
  class-pr-member-pages.php       # Huvudklass för pluginlogik
  traits/
    trait-prmp-actions.php        # Åtgärdsrelaterad logik
    trait-prmp-admin-editor.php   # Adminredigeringsfunktioner
    trait-prmp-admin-settings.php # Inställningshantering
    trait-prmp-flash.php          # Flash-meddelanden
    trait-prmp-options.php        # Alternativhantering
    trait-prmp-pages.php          # Sidhatering
    trait-prmp-restrictions.php   # Åtkomstbegränsningar
    trait-prmp-shortcodes.php     # Kortkoder
```

## Installation
1. Ladda upp mappen till din WordPress-pluginskatalog (`wp-content/plugins`).
2. Aktivera tillägget via WordPress adminpanel.

## Användning
- Gå till inställningssidan för att konfigurera medlemssidor och åtkomst.
- Använd tillgängliga kortkoder i dina sidor eller inlägg.

## Bidra
Pull requests och förbättringsförslag är välkomna!

## Licens
MIT-licens.
