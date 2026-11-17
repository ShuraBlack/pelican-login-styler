<?php

namespace ShuraBlack\LoginStyler;

use App\Contracts\Plugins\HasPluginSettings;
use App\Traits\EnvironmentWriterTrait;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Schemas\Components\Section;
use Filament\View\PanelsRenderHook;
use Throwable;

class LoginStylerPlugin implements HasPluginSettings, Plugin
{
    use EnvironmentWriterTrait;

    /** option => [label, background-size, background-repeat] */
    private const IMAGE_FITS = [
        'cover' => ['Fill screen', 'cover', 'no-repeat'],
        'contain' => ['Show whole image', 'contain', 'no-repeat'],
        'tile' => ['Tile', 'auto', 'repeat'],
    ];

    private const IMAGE_POSITIONS = [
        'center' => 'Center',
        'top' => 'Top',
        'bottom' => 'Bottom',
        'left' => 'Left',
        'right' => 'Right',
    ];

    public function getId(): string
    {
        return 'login-styler';
    }

    public function register(Panel $panel): void
    {
        if (!$panel->hasLogin()) {
            return;
        }

        // Plugins load after Pelican's panel setup, so this replaces its login page.
        $panel->login(StyledLogin::class);

        // Scoped to the login page class, so no other page gets the CSS or logo.
        $panel->renderHook(PanelsRenderHook::HEAD_END, function (): string {
            $css = self::buildCss($this->getSettingsFormData());

            return $css === '' ? '' : '<style id="login-styler">' . $css . '</style>';
        }, scopes: StyledLogin::class);

        // Sits between the "Sign in" heading and the form.
        $panel->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, function (): string {
            $logo = config('login-styler.logo');
            if (blank($logo)) {
                return '';
            }
            $height = (float) (config('login-styler.logo_height') ?: 80);

            return '<img src="' . e($logo) . '" alt="" class="login-styler-logo" style="display:block;margin:0 auto;max-width:100%;height:' . $height . 'px;object-fit:contain">';
        }, scopes: StyledLogin::class);
    }

    public function boot(Panel $panel): void {}

    public function getSettingsFormData(): array
    {
        return config('login-styler', []);
    }

    public function getSettingsForm(): array
    {
        return [
            Section::make('Texts & logo')->schema([
                TextInput::make('title')->label('Title')
                    ->helperText('Replaces the panel name above the heading. Empty = panel name. Not shown if the panel has a logo image.'),
                TextInput::make('heading')->label('Heading')->placeholder('Sign in'),
                TextInput::make('logo')->label('Logo URL')->url()
                    ->helperText('Optional image shown below the heading. Style it with the .login-styler-logo class.'),
                TextInput::make('logo_height')->label('Logo height')->numeric()->minValue(1)->suffix('px')->placeholder('80'),
            ]),
            Section::make('Background')->schema([
                ColorPicker::make('background_color')->label('Background color'),
                TextInput::make('background_image')->label('Background image URL')->url(),
                Select::make('image_fit')->label('Image fit')->options(array_map(fn ($fit) => $fit[0], self::IMAGE_FITS))->placeholder('Fill screen'),
                Select::make('image_position')->label('Image position')->options(self::IMAGE_POSITIONS)->placeholder('Center'),
                TextInput::make('image_dim')->label('Image overlay')->numeric()->minValue(0)->maxValue(100)->suffix('%')->placeholder('0')
                    ->helperText('Fades the image into the background color (black if none), e.g. to darken it.'),
            ]),
            CodeEditor::make('custom_css')->label('Custom CSS')->language(Language::Css)->columnSpanFull()
                ->helperText('Injected as-is after the background, only on the login page. Tip: prefix selectors with ":root" and use !important to beat themes.'),
        ];
    }

    public function saveSettings(array $data): void
    {
        $values = [];
        foreach (array_keys(config('login-styler', [])) as $key) {
            $values['LOGIN_STYLER_' . strtoupper($key)] = (string) ($data[$key] ?? '');
        }
        // .env values must stay on one line; the config file decodes it again.
        $values['LOGIN_STYLER_CUSTOM_CSS'] = rawurlencode($values['LOGIN_STYLER_CUSTOM_CSS']);

        // The panel swallows exceptions from saveSettings, so report failure ourselves.
        try {
            $this->writeToEnvironment($values);
        } catch (Throwable $exception) {
            Notification::make()->title('Could not save settings')->body($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Login style saved')->success()->send();
    }

    /** @param  array<string, mixed>  $s */
    public static function buildCss(array $s): string
    {
        $v = fn (string $key) => filled($s[$key] ?? null) ? (string) $s[$key] : null;
        $color = $v('background_color');
        $image = $v('background_image');

        // Lookups, not raw values: only known CSS keywords reach the stylesheet.
        $fit = self::IMAGE_FITS[$v('image_fit') ?? ''] ?? self::IMAGE_FITS['cover'];
        $position = array_key_exists($v('image_position') ?? '', self::IMAGE_POSITIONS) ? $v('image_position') : 'center';
        $dim = min(100, max(0, (float) ($v('image_dim') ?? 0)));
        $overlay = 'color-mix(in srgb, ' . ($color ?? '#000') . " {$dim}%, transparent)";

        $declarations = array_filter([
            'background-color' => $color,
            // First layer: overlay that fades the image into the background color.
            'background-image' => $image ? "linear-gradient($overlay, $overlay), url(" . json_encode($image, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ')' : null,
            'background-size' => $image ? "auto, {$fit[1]}" : null,
            'background-repeat' => $image ? "no-repeat, {$fit[2]}" : null,
            'background-position' => $image ? "center, $position" : null,
            'background-attachment' => $image ? 'fixed' : null,
        ], fn ($value) => $value !== null);

        $css = '';
        if ($declarations) {
            // ":root" + !important beat theme plugins; ":has" because SPA navigation keeps this <style> after login.
            $css .= ':root body:has(.fi-simple-layout){'
                . implode(';', array_map(fn ($prop, $value) => "$prop:$value !important", array_keys($declarations), $declarations))
                . '}';
        }
        $css .= $v('custom_css') ?? '';

        // Keep the CSS from closing the <style> tag and injecting HTML.
        return str_replace('</', '<\/', $css);
    }
}
