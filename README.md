# Login Styler

Restyle the Pelican login page: set a background color or image, and add your own CSS.

## Settings

Open **Admin → Plugins → Login Styler → Settings**.

### Background

| Setting | Description |
|---|---|
| Background color | Page background color. |
| Background image URL | Direct link to an image (it must open as just the image in a browser). |
| Image fit | Fill screen, show whole image, or tile. |
| Image position | Center, top, bottom, left or right. |
| Image overlay | 0–100 %. Fades the image into the background color (black if none). Use it to darken a bright image. |

### Custom CSS

The CSS is added after the background rules, on the login page only. Anything you can select, you can style.

To beat a theme that uses `!important` too, prefix your selectors with `:root`:

```css
:root .fi-simple-main {
    border-radius: 18px !important;
    background-color: transparent !important;
    backdrop-filter: blur(12px);
}
```

Useful selectors:

- `.fi-simple-main` (also `#fi-main-content`): the login card
- `.fi-simple-header-heading`: the "Sign in" heading
- `.fi-simple-main button[type="submit"]`: the login button

## Notes

- Settings are stored in `storage/app/login-styler.json`, so plugin updates keep them.
- The CSS applies to the login page only. Because the panel runs in SPA mode, custom CSS can stay active after logging in until the next full page reload. Scope rules with `body:has(.fi-simple-layout)` if that matters.
- Images on `http://` are blocked on an `https://` panel. Some hosts also block embedding their images on other sites.
