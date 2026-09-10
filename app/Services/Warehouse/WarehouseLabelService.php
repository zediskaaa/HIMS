<?php

namespace App\Services\Warehouse;

use App\Enums\AuditAction;
use App\Models\StorageLocation;
use App\Models\User;
use App\Models\WarehouseLabelPrint;
use App\Models\WarehouseTask;
use App\Services\AuditLogger;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WarehouseLabelService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function create(Model $target, string $template, int $copies, User $actor): WarehouseLabelPrint
    {
        [$type, $code, $description] = match (true) {
            $target instanceof StorageLocation => ['location', $target->barcode_value ?: $target->code, $target->fullPath()],
            $target instanceof WarehouseTask => ['task', $target->task_number, $target->task_type->label()],
            default => throw new \DomainException('Unsupported warehouse label target.'),
        };

        $label = WarehouseLabelPrint::create([
            'label_number' => 'LBL-'.now()->format('Ymd').'-'.Str::ulid(),
            'target_type' => $type,
            'target_id' => $target->getKey(),
            'template' => $template,
            'payload' => ['code' => $code, 'description' => $description, 'origin' => 'HIMS internal identifier'],
            'copies' => $copies,
            'printed_by_id' => $actor->id,
            'printed_at' => now(),
        ]);

        $this->auditLogger->record(AuditAction::PrintedWarehouseLabel, actor: $actor, target: $label,
            description: "Generated internal warehouse label {$label->label_number}",
            newValues: ['target_type' => $type, 'target_id' => $target->getKey(), 'copies' => $copies]);

        return $label;
    }

    public function qrDataUri(string $value): string
    {
        $renderer = new ImageRenderer(new RendererStyle(240, 4), new SvgImageBackEnd);

        return 'data:image/svg+xml;base64,'.base64_encode((new Writer($renderer))->writeString($value));
    }
}
