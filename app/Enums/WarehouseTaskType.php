<?php

namespace App\Enums;

enum WarehouseTaskType: string
{
    case PutAway = 'put_away';
    case Replenishment = 'replenishment';
    case Pick = 'pick';
    case Pack = 'pack';
    case Stage = 'stage';
    case Dispatch = 'dispatch';
    case Move = 'move';
    case TransferDispatch = 'transfer_dispatch';
    case TransferReceipt = 'transfer_receipt';
    case CycleCount = 'cycle_count';
    case ExceptionResolution = 'exception_resolution';
    case RecallRetrieval = 'recall_retrieval';

    public function label(): string
    {
        return match ($this) {
            self::PutAway => 'Put-away',
            self::Replenishment => 'Pick-face replenishment',
            self::Pick => 'Picking',
            self::Pack => 'Packing verification',
            self::Stage => 'Dispatch staging',
            self::Dispatch => 'Dispatch / handover',
            self::Move => 'Internal movement',
            self::TransferDispatch => 'Transfer dispatch',
            self::TransferReceipt => 'Transfer receipt',
            self::CycleCount => 'Cycle count',
            self::ExceptionResolution => 'Exception resolution',
            self::RecallRetrieval => 'Recall retrieval',
        };
    }

    public function movesStock(): bool
    {
        return in_array($this, [self::PutAway, self::Replenishment, self::Pick, self::Move], true);
    }
}
