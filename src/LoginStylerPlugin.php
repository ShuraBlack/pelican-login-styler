<?php

namespace ShuraBlack\LoginStyler;

use App\Contracts\Plugins\HasPluginSettings;
use App\Filament\Pages\Auth\Login;
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

class LoginStylerPlugin implements HasPluginSettings, Plugin
{
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
        // Scoped to the login page class, so no other page gets the CSS.
        $panel->renderHook(PanelsRenderHook::HEAD_END, function (): string {
            $css = self::buildCss($this->getSettingsFormData());

            return $css === '' ? '' : '<style id="login-styler">' . $css . '</style>';
        }, scopes: Login::class);
    }

    public function boot(Panel $panel): void {}

    public function getSettingsFormData(): array
    {
        $path = self::settingsPath();

        return is_file($path) ? (json_decode((string) file_get_contents($path), true) ?? []) : [];
    }

    public function getSettingsForm(): array
    {
        return [
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
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // The panel swallows exceptions from saveSettings, so report failure ourselves.
        if (@file_put_contents(self::settingsPath(), $json) === false) {
            Notification::make()->title('Could not write ' . self::settingsPath())->danger()->send();

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

    private static function settingsPath(): string
    {
        return storage_path('app/login-styler.json');
    }
}
