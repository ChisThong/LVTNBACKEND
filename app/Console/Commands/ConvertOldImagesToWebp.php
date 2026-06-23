<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\HinhAnh;
use App\Models\Shop;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ConvertOldImagesToWebp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:convert-webp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert existing product images and shop banners to WebP format for storage optimization';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting image conversion process to WebP...');

        // Initialize Intervention Image Manager with GD Driver (v3)
        $manager = new ImageManager(new Driver());

        // ─── 1. XỬ LÝ ẢNH SẢN PHẨM (HINHANH) ───
        $this->info('--- Scanning product images... ---');
        $images = HinhAnh::where('HinhAnh', 'not like', '%.webp')->get();
        $totalImages = $images->count();
        $convertedImages = 0;
        $failedImages = 0;

        if ($totalImages > 0) {
            $this->info("Found {$totalImages} product images to convert.");
            $progressBar = $this->output->createProgressBar($totalImages);
            $progressBar->start();

            foreach ($images as $imageRecord) {
                $oldPath = $imageRecord->HinhAnh;

                if (!Storage::disk('public')->exists($oldPath)) {
                    $failedImages++;
                    Log::warning("Product image file does not exist: {$oldPath}");
                    $progressBar->advance();
                    continue;
                }

                try {
                    $fullPath = Storage::disk('public')->path($oldPath);
                    $image = $manager->read($fullPath);
                    $webpContent = $image->toWebp(80);

                    $filename = pathinfo($oldPath, PATHINFO_FILENAME);
                    $newPath = 'products/' . $filename . '.webp';

                    Storage::disk('public')->put($newPath, (string) $webpContent);

                    $imageRecord->HinhAnh = $newPath;
                    $imageRecord->save();

                    if ($oldPath !== $newPath) {
                        Storage::disk('public')->delete($oldPath);
                    }

                    $convertedImages++;
                } catch (\Exception $e) {
                    $failedImages++;
                    Log::error("Failed to convert product image {$oldPath}: " . $e->getMessage());
                }

                $progressBar->advance();
            }
            $progressBar->finish();
            $this->newLine();
        } else {
            $this->info('No product images to convert.');
        }

        // ─── 2. XỬ LÝ BANNER CỦA SHOP (SHOP BANER) ───
        $this->newLine();
        $this->info('--- Scanning shop banners... ---');
        $shops = Shop::whereNotNull('baner')
            ->where('baner', '!=', '')
            ->where('baner', 'not like', '%.webp')
            ->get();
        $totalShops = $shops->count();
        $convertedShops = 0;
        $failedShops = 0;

        if ($totalShops > 0) {
            $this->info("Found {$totalShops} shop banners to convert.");
            $progressBarShops = $this->output->createProgressBar($totalShops);
            $progressBarShops->start();

            foreach ($shops as $shop) {
                $oldPath = $shop->baner;

                if (!Storage::disk('public')->exists($oldPath)) {
                    $failedShops++;
                    Log::warning("Shop banner file does not exist: {$oldPath}");
                    $progressBarShops->advance();
                    continue;
                }

                try {
                    $fullPath = Storage::disk('public')->path($oldPath);
                    $image = $manager->read($fullPath);
                    $webpContent = $image->toWebp(80);

                    $filename = pathinfo($oldPath, PATHINFO_FILENAME);
                    
                    // Maintain standard shops/baner/ directory path
                    $newPath = 'shops/baner/' . $filename . '.webp';

                    Storage::disk('public')->put($newPath, (string) $webpContent);

                    $shop->baner = $newPath;
                    $shop->save();

                    if ($oldPath !== $newPath) {
                        Storage::disk('public')->delete($oldPath);
                    }

                    $convertedShops++;
                } catch (\Exception $e) {
                    $failedShops++;
                    Log::error("Failed to convert shop banner {$oldPath}: " . $e->getMessage());
                }

                $progressBarShops->advance();
            }
            $progressBarShops->finish();
            $this->newLine();
        } else {
            $this->info('No shop banners to convert.');
        }

        $this->newLine();
        $this->info("Process completed successfully!");
        $this->info("Product Images Converted: {$convertedImages} (Failed: {$failedImages})");
        $this->info("Shop Banners Converted: {$convertedShops} (Failed: {$failedShops})");

        return 0;
    }
}
