<?php

namespace App\Support;

use App\Enums\SecurityAnswerType;
use App\Models\SecurityChecklistItem;
use App\Models\SecurityChecksheet;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Arr;

/**
 * Checklist Security Checksheet ala Google Form: pertanyaan dari master
 * SecurityChecklistItem, jawaban disimpan bersama salinan pertanyaannya
 * (snapshot) supaya laporan lama tidak ikut berubah saat pertanyaan diedit.
 *
 * Isian form: checklist.{kunci} => jawaban, kunci = "q{item_id}".
 */
final class SecurityChecklist
{
    /**
     * Pertanyaan aktif untuk laporan baru, sesuai urutan yang diatur admin.
     *
     * @return list<array<string, mixed>>
     */
    public static function activeDefinitions(): array
    {
        return SecurityChecklistItem::query()
            ->active()
            ->ordered()
            ->get()
            ->map(fn (SecurityChecklistItem $item): array => $item->definition())
            ->values()
            ->all();
    }

    /**
     * Pertanyaan yang tersimpan di laporan, untuk mengedit laporan itu.
     *
     * @return list<array<string, mixed>>
     */
    public static function recordDefinitions(SecurityChecksheet $record): array
    {
        return array_map(
            fn (array $entry): array => Arr::except($entry, ['answer', 'matches']),
            $record->checklistEntries(),
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function key(array $definition, int $index): string
    {
        return filled($definition['item_id'] ?? null) ? 'q'.$definition['item_id'] : 'n'.$index;
    }

    /**
     * Satu section per bagian, butir bernomor sesuai urutan.
     *
     * @param  list<array<string, mixed>>  $definitions
     * @return list<Component>
     */
    public static function formSections(array $definitions): array
    {
        if ($definitions === []) {
            return [
                Callout::make('Belum ada pertanyaan checklist')
                    ->description('Minta admin menambahkan pertanyaan di menu Security › Pertanyaan Checksheet.')
                    ->warning(),
            ];
        }

        $sections = [];

        foreach (self::groupBySection($definitions) as $number => $group) {
            // Satu pertanyaan satu baris: pertanyaan di kiri, jawaban di kanan.
            $sections[] = Section::make(($number + 1).'. '.$group['title'])
                ->compact()
                ->columnSpan(1)
                ->schema(array_map(
                    fn (array $item): Field => self::field($item['definition'], $item['index']),
                    $group['items'],
                ));
        }

        return $sections;
    }

    /**
     * Kelompokkan pertanyaan per bagian sesuai kemunculan pertamanya.
     *
     * @param  list<array<string, mixed>>  $definitions
     * @return list<array{title: string, items: list<array{index: int, definition: array<string, mixed>}>}>
     */
    public static function groupBySection(array $definitions): array
    {
        $groups = [];

        foreach ($definitions as $index => $definition) {
            $title = (string) ($definition['section'] ?? '-');
            $groups[$title] ??= ['title' => $title, 'items' => []];
            $groups[$title]['items'][] = ['index' => $index, 'definition' => $definition];
        }

        return array_values($groups);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function field(array $definition, int $index): Field
    {
        $name = 'checklist.'.self::key($definition, $index);
        $type = SecurityAnswerType::tryFrom((string) ($definition['type'] ?? '')) ?? SecurityAnswerType::Text;
        $options = self::options($definition);

        // Pilihan berjajar ke samping supaya form tidak memanjang ke bawah.
        $field = match ($type) {
            SecurityAnswerType::Choice => Radio::make($name)->options($options)->inline(),
            SecurityAnswerType::YesNo => ToggleButtons::make($name)->options(SecurityAnswerType::yesNoOptions())->inline(),
            SecurityAnswerType::Checkboxes => CheckboxList::make($name)->options($options)->columns(2),
            SecurityAnswerType::Text => TextInput::make($name)->placeholder('Tulis kondisinya')->maxLength(255),
        };

        return $field
            ->label((string) $definition['question'])
            ->inlineLabel()
            ->validationAttribute((string) $definition['question'])
            ->required();
    }

    /**
     * Gabungkan pertanyaan dengan jawaban isian form menjadi snapshot laporan.
     *
     * @param  list<array<string, mixed>>  $definitions
     * @param  array<string, mixed>  $answers
     * @return array{checklist: list<array<string, mixed>>, mismatch_count: int}
     */
    public static function build(array $definitions, array $answers): array
    {
        $entries = [];

        foreach ($definitions as $index => $definition) {
            $answer = self::normalize($definition, $answers[self::key($definition, $index)] ?? null);

            $entries[] = [
                ...Arr::only($definition, ['item_id', 'section', 'question', 'type', 'options', 'answer_key']),
                'answer' => $answer,
                'matches' => self::evaluate($definition, $answer),
            ];
        }

        return [
            'checklist' => $entries,
            'mismatch_count' => count(array_filter($entries, fn (array $entry): bool => $entry['matches'] === false)),
        ];
    }

    /**
     * Isian form dari snapshot laporan.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, mixed>
     */
    public static function answers(array $entries): array
    {
        $answers = [];

        foreach ($entries as $index => $entry) {
            $answers[self::key($entry, $index)] = $entry['answer'] ?? null;
        }

        return $answers;
    }

    /**
     * true = sesuai kunci, false = tidak sesuai, null = tidak dinilai
     * (teks bebas, kunci kosong, atau belum dijawab).
     *
     * @param  array<string, mixed>  $definition
     */
    public static function evaluate(array $definition, mixed $answer): ?bool
    {
        $type = SecurityAnswerType::tryFrom((string) ($definition['type'] ?? ''));
        $key = $definition['answer_key'] ?? null;

        if (! $type?->hasAnswerKey() || blank($key) || blank($answer)) {
            return null;
        }

        if ($type->isMultiple()) {
            $expected = array_map('strval', (array) $key);
            $given = array_map('strval', (array) $answer);
            sort($expected);
            sort($given);

            return $expected === $given;
        }

        return (string) $answer === (string) $key;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, string>
     */
    private static function options(array $definition): array
    {
        $options = array_values(array_filter(
            array_map('strval', (array) ($definition['options'] ?? [])),
            fn (string $option): bool => $option !== '',
        ));

        return array_combine($options, $options);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private static function normalize(array $definition, mixed $answer): mixed
    {
        if (SecurityAnswerType::tryFrom((string) ($definition['type'] ?? ''))?->isMultiple()) {
            $given = array_map('strval', array_filter((array) $answer, fn (mixed $value): bool => filled($value)));
            $options = array_keys(self::options($definition));

            // Disimpan sesuai urutan opsi, bukan urutan centang, agar tampil konsisten.
            return array_values(array_unique([
                ...array_intersect($options, $given),
                ...array_diff($given, $options),
            ]));
        }

        if (is_array($answer)) {
            $answer = implode(', ', $answer);
        }

        return filled($answer) ? trim((string) $answer) : null;
    }
}
