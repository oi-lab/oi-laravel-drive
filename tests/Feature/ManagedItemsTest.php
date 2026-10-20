<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OiLab\OiLaravelDrive\Exceptions\DriveException;
use OiLab\OiLaravelDrive\Services\DriveArchives;
use OiLab\OiLaravelDrive\Services\DriveFiles;
use OiLab\OiLaravelDrive\Services\DriveFolders;
use OiLab\OiLaravelDrive\Services\DriveLocks;
use OiLab\OiLaravelDrive\Services\DriveManager;
use OiLab\OiLaravelDrive\Services\DrivePresenter;
use OiLab\OiLaravelDrive\Tests\Fixtures\Team;

beforeEach(function () {
    ['team' => $this->team, 'user' => $this->user, 'drive' => $this->drive] = driveContext();
    $this->folders = app(DriveFolders::class);
    $this->files = app(DriveFiles::class);
    $this->drives = app(DriveManager::class);
});

it('ensures a folder by its key: created once, then brought back as the host wants it', function () {
    $year = $this->folders->ensure($this->drive, 'fy:2026', '2026');
    $purchases = $this->folders->ensure($this->drive, 'fy:2026/purchases', 'Achats', $year, sealed: true);

    expect($this->folders->ensure($this->drive, 'fy:2026/purchases', 'Achats', $year, sealed: true)->id)->toBe($purchases->id)
        ->and($this->drives->folderQuery($this->drive)->count())->toBe(2)
        ->and($purchases->driveEntry->key)->toBe('fy:2026/purchases')
        ->and($purchases->driveEntry->locked)->toBeTrue()
        ->and($purchases->driveEntry->sealed)->toBeTrue();

    DriveLocks::bypass(function () use ($purchases) {
        $this->folders->update($purchases, 'Renommé');
        $this->folders->move($purchases, null);
        $this->folders->delete($purchases);
    });

    $again = $this->folders->ensure($this->drive, 'fy:2026/purchases', 'Achats', $year, sealed: true);

    expect($again->id)->toBe($purchases->id)
        ->and($again->trashed())->toBeFalse()
        ->and($again->name)->toBe('Achats')
        ->and($again->parent_id)->toBe($year->id);
});

it('refuses users to rename, move or delete a locked folder, or a folder holding one', function () {
    $year = $this->folders->ensure($this->drive, 'fy:2026', '2026');
    $other = $this->folders->create($this->drive, 'Perso');
    $holder = $this->folders->create($this->drive, 'Classeur');
    DriveLocks::bypass(fn () => $this->folders->ensure($this->drive, 'inside', 'Dedans', $holder));

    expect(fn () => $this->folders->update($year, 'Autre'))->toThrow(DriveException::class, 'géré par l’application')
        ->and(fn () => $this->folders->move($year, $other))->toThrow(DriveException::class)
        ->and(fn () => $this->folders->delete($year))->toThrow(DriveException::class)
        ->and(fn () => $this->folders->delete($year, force: true))->toThrow(DriveException::class)
        ->and(fn () => $this->folders->delete($holder))->toThrow(DriveException::class, 'contient des éléments');

    $this->folders->create($this->drive, 'Notes', $year);
    expect($this->drives->folderQuery($this->drive)->where('parent_id', $year->id)->count())->toBe(1);
});

it('takes no folder or file from users in a sealed folder, nor lets them take one out', function () {
    $sealed = $this->folders->ensure($this->drive, 'sales', 'Ventes', sealed: true);
    $open = $this->folders->create($this->drive, 'Libre');
    $file = $this->files->storeContent($this->drive, 'x', 'x.txt', [$open]);

    expect(fn () => $this->folders->create($this->drive, 'Sous-dossier', $sealed))->toThrow(DriveException::class, 'Ventes')
        ->and(fn () => $this->files->storeContent($this->drive, 'y', 'y.txt', [$sealed]))->toThrow(DriveException::class)
        ->and(fn () => $this->files->attach($file, [$sealed]))->toThrow(DriveException::class)
        ->and(fn () => $this->folders->move($open, $sealed))->toThrow(DriveException::class);

    DriveLocks::bypass(fn () => $this->files->attach($file, [$sealed]));

    expect(fn () => $this->files->detach($file, [$sealed]))->toThrow(DriveException::class)
        ->and(fn () => $this->files->sync($file, [$open]))->toThrow(DriveException::class)
        ->and(fn () => $this->files->delete($file))->toThrow(DriveException::class);

    $this->files->detach($file, [$open]);
    expect(app(DrivePresenter::class)->file($file->fresh())->folder_ids)->toBe([$sealed->uuid]);
});

it('links the stored object of another model without copying it, and never deletes it', function () {
    Storage::disk('drive')->put('documents/invoice-1.pdf', '%PDF-1.4 facture');
    $sales = $this->folders->ensure($this->drive, 'sales', 'Ventes', sealed: true);
    $invoice = Team::query()->create(['name' => 'Facture 1']);

    $link = $this->files->link($this->drive, $invoice, 'documents/invoice-1.pdf', '20260115_acme_F-1.pdf', [$sales], mimetype: 'application/pdf');
    $data = app(DrivePresenter::class)->file($link);

    expect($link->filename_disk)->toBe('documents/invoice-1.pdf')
        ->and($data->is_link)->toBeTrue()
        ->and($data->locked)->toBeTrue()
        ->and($data->source_id)->toBe((string) $invoice->id)
        ->and($data->size)->toBe(16)
        ->and($data->folder_ids)->toBe([$sales->uuid])
        ->and(Storage::disk('drive')->allFiles('drive'))->toBe([])
        ->and(fn () => $this->files->delete($link))->toThrow(DriveException::class)
        ->and(fn () => $this->files->update($link, ['name' => 'autre.pdf']))->toThrow(DriveException::class);

    $zip = new ZipArchive;
    $zip->open(app(DriveArchives::class)->build($sales));
    expect($zip->getFromName('20260115_acme_F-1.pdf'))->toBe('%PDF-1.4 facture');
    $zip->close();

    Storage::disk('drive')->put('documents/invoice-1-facturx.pdf', '%PDF-1.7 factur-x');
    $archive = $this->folders->ensure($this->drive, 'archive', 'Archive');
    $again = $this->files->link($this->drive, $invoice, 'documents/invoice-1-facturx.pdf', '20260115_acme_F-1.pdf', [$archive]);

    expect($again->id)->toBe($link->id)
        ->and($again->filename_disk)->toBe('documents/invoice-1-facturx.pdf')
        ->and($again->filesize)->toBe(17)
        ->and(app(DrivePresenter::class)->file($again->fresh())->folder_ids)->toBe([$archive->uuid]);

    expect($this->files->unlink($invoice))->toBe(1)
        ->and($this->drives->linksOf($invoice, withTrashed: true))->toHaveCount(0);

    Storage::disk('drive')->assertExists('documents/invoice-1-facturx.pdf');
});

it('keeps the object of a link when the trash is emptied', function () {
    Storage::disk('drive')->put('documents/receipt.pdf', '%PDF');
    $receipt = Team::query()->create(['name' => 'Justificatif']);
    $link = $this->files->link($this->drive, $receipt, 'documents/receipt.pdf', 'ticket.pdf', locked: false);

    $this->files->delete($link);
    $this->drives->emptyTrash($this->drive);

    expect($this->drives->linksOf($receipt, withTrashed: true))->toHaveCount(0);
    Storage::disk('drive')->assertExists('documents/receipt.pdf');
});

it('writes the notice of a folder, given with its text in the listing', function () {
    $banks = $this->folders->ensure($this->drive, 'banks', 'Banques');
    $notice = $this->files->notice($this->drive, $banks, "# Banques\n\nDéposez ici vos relevés.");
    $path = $notice->filename_disk;

    $listing = $this->drives->contents($this->drive, $banks);

    expect($listing->notice->text)->toBe("# Banques\n\nDéposez ici vos relevés.")
        ->and($listing->notice->file->role)->toBe('notice')
        ->and($listing->notice->file->locked)->toBeTrue()
        ->and(collect($listing->files)->pluck('name')->all())->toBe(['Notice.md'])
        ->and(fn () => $this->files->delete($notice))->toThrow(DriveException::class);

    expect($this->files->notice($this->drive, $banks, "# Banques\n\nDéposez ici vos relevés.")->filename_disk)->toBe($path);

    $rewritten = $this->files->notice($this->drive, $banks, '# Banques et relevés');

    expect($rewritten->id)->toBe($notice->id)
        ->and($this->drives->contents($this->drive, $banks)->notice->text)->toBe('# Banques et relevés');
    Storage::disk('drive')->assertMissing($path);

    $this->files->store($this->drive, UploadedFile::fake()->createWithContent('releve.pdf', '%PDF'), [$banks]);
    expect($this->drives->contents($this->drive, $banks)->notice)->not->toBeNull()
        ->and($this->drives->contents($this->drive)->notice)->toBeNull();
});

it('answers the API with the lock rule and lists the notice', function () {
    $year = $this->folders->ensure($this->drive, 'fy:2026', '2026', sealed: true);
    $this->files->notice($this->drive, $year, '# Exercice 2026');

    $this->deleteJson('/api/drive/folders/'.$year->uuid)
        ->assertForbidden()
        ->assertJsonPath('error', 'locked');

    $this->postJson('/api/drive/folders', ['name' => 'Ajout', 'parent_id' => $year->uuid])
        ->assertForbidden()
        ->assertJsonPath('error', 'sealed');

    $this->getJson('/api/drive/contents?folder='.$year->uuid)
        ->assertOk()
        ->assertJsonPath('folder.locked', true)
        ->assertJsonPath('folder.sealed', true)
        ->assertJsonPath('folder.key', 'fy:2026')
        ->assertJsonPath('notice.text', '# Exercice 2026');
});
