import React from 'react';
import { DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

interface ModalButtonsProps {
    onCancel: () => void;
    cancelLabel?: string;
    saveLabel?: string;
    processing?: boolean;
}

export function ModalButtons({
    onCancel,
    cancelLabel = 'Cancel',
    saveLabel = 'Save',
    processing = false,
}: ModalButtonsProps) {
    return (
        <DialogFooter className="flex items-center justify-end gap-2">
            <Button
                type="button"
                variant="outline"
                onClick={onCancel}
                className="font-semibold text-xs"
                disabled={processing}
            >
                {cancelLabel}
            </Button>
            <Button
                type="submit"
                className="font-semibold text-xs"
                disabled={processing}
            >
                {processing ? 'Saving...' : saveLabel}
            </Button>
        </DialogFooter>
    );
}
