<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Rename store_locations to branches
        if (Schema::hasTable('store_locations') && ! Schema::hasTable('branches')) {
            Schema::rename('store_locations', 'branches');
        }
        // Add `name` column to branches if missing (Branch model expects it; store_locations had name_ar/name_en only)
        if (Schema::hasTable('branches') && ! Schema::hasColumn('branches', 'name')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->string('name')->nullable()->after('merchant_id');
            });
        }
        if (Schema::hasTable('branches') && ! Schema::hasColumn('branches', 'mall_id')) {
            Schema::table('branches', function (Blueprint $table) {
                $after = Schema::hasColumn('branches', 'name') ? 'name' : 'merchant_id';
                $table->foreignId('mall_id')
                    ->nullable()
                    ->after($after)
                    ->constrained('malls')
                    ->nullOnDelete();
            });
        }

        // 2. Refactor offers table
        Schema::table('offers', function (Blueprint $table) {
            // Rename existing columns if they exist
            if (Schema::hasColumn('offers', 'title_ar')) {
                $table->renameColumn('title_ar', 'title');
            }
            if (Schema::hasColumn('offers', 'images')) {
                $table->renameColumn('images', 'offer_images');
            }
            if (Schema::hasColumn('offers', 'start_at')) {
                $table->renameColumn('start_at', 'start_date');
            }
            if (Schema::hasColumn('offers', 'end_at')) {
                $table->renameColumn('end_at', 'end_date');
            }
            if (Schema::hasColumn('offers', 'description_ar')) {
                $table->renameColumn('description_ar', 'description');
            }

            // Remove old location relation
            if (Schema::hasColumn('offers', 'location_id')) {
                // MySQL: drop FK before index (index backs the FK). SQLite: drop
                // index before column. Use FK-first order; catch for SQLite if needed.
                try {
                    $table->dropForeign(['location_id']);
                } catch (Throwable $e) {
                    // FK may not exist on every environment — safe to ignore.
                }
                try {
                    $table->dropIndex('offers_location_id_index');
                } catch (Throwable $e) {
                    // Index didn't exist or SQLite path — safe to ignore.
                }
                $table->dropColumn('location_id');
            }

            // Add or modify columns only if missing
            if (! Schema::hasColumn('offers', 'discount')) {
                $table->decimal('discount', 10, 2)->after('price')->default(0);
            }
            if (! Schema::hasColumn('offers', 'location')) {
                $table->json('location')->nullable()->after('end_date');
            }

            // Adjust status to string if needed
            if (Schema::hasColumn('offers', 'status')) {
                $table->string('status')->default('active')->change();
            }
            // Soft deletes (Offer model uses SoftDeletes)
            if (! Schema::hasColumn('offers', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (Schema::hasTable('offers')
            && Schema::hasColumn('offers', 'start_date')
            && Schema::hasColumn('offers', 'end_date')
            && ! Schema::hasIndex('offers', 'offers_status_dates_index')) {
            Schema::table('offers', function (Blueprint $table) {
                $table->index(['status', 'start_date', 'end_date'], 'offers_status_dates_index');
            });
        }

        // 3. Refactor coupons table (add columns only if missing; earlier migrations may have added some)
        // order_id stays nullable on coupons for checkout / admin order counts.
        Schema::table('coupons', function (Blueprint $table) {
            if (! Schema::hasColumn('coupons', 'image')) {
                $table->string('image')->nullable()->after('offer_id');
            }
            if (! Schema::hasColumn('coupons', 'title')) {
                $table->string('title')->after('image');
            }
            if (! Schema::hasColumn('coupons', 'description')) {
                $table->text('description')->nullable()->after('title');
            }
            if (! Schema::hasColumn('coupons', 'price')) {
                $table->decimal('price', 10, 2)->default(0)->after('description');
            }
            if (! Schema::hasColumn('coupons', 'discount')) {
                $table->decimal('discount', 10, 2)->default(0)->after('price');
            }
            if (! Schema::hasColumn('coupons', 'barcode')) {
                $table->string('barcode')->unique()->after('discount');
            }
            if (! Schema::hasColumn('coupons', 'expires_at')) {
                $table->dateTime('expires_at')->nullable()->after('barcode');
            }

            // Adjust status to string if needed (skip if already string)
            if (Schema::hasColumn('coupons', 'status')) {
                $table->string('status')->default('active')->change();
            }
        });

        // Bilingual coupon fields (was 2026_03_30_140000)
        Schema::table('coupons', function (Blueprint $table) {
            if (! Schema::hasColumn('coupons', 'title_ar')) {
                $table->string('title_ar')->nullable();
            }
            if (! Schema::hasColumn('coupons', 'title_en')) {
                $table->string('title_en')->nullable();
            }
            if (! Schema::hasColumn('coupons', 'description_ar')) {
                $table->text('description_ar')->nullable();
            }
            if (! Schema::hasColumn('coupons', 'description_en')) {
                $table->text('description_en')->nullable();
            }
            if (! Schema::hasColumn('coupons', 'starts_at')) {
                $table->dateTime('starts_at')->nullable();
            }
        });

        // 4. Create offer_branch pivot table (if not exists)
        if (! Schema::hasTable('offer_branch')) {
            Schema::create('offer_branch', function (Blueprint $table) {
                $table->id();
                $table->foreignId('offer_id')->constrained()->onDelete('cascade');
                $table->foreignId('branch_id')->constrained()->onDelete('cascade');
                $table->timestamps();
            });
        }

        // 5. Create favorites (offer_user) pivot table (if not exists)
        if (! Schema::hasTable('offer_user')) {
            Schema::create('offer_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('offer_id')->constrained()->onDelete('cascade');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->timestamps();

                $table->unique(['offer_id', 'user_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offer_user');
        Schema::dropIfExists('offer_branch');

        Schema::table('coupons', function (Blueprint $table) {
            $dropBilingual = [];
            foreach (['title_ar', 'title_en', 'description_ar', 'description_en', 'starts_at'] as $col) {
                if (Schema::hasColumn('coupons', $col)) {
                    $dropBilingual[] = $col;
                }
            }
            if ($dropBilingual !== []) {
                $table->dropColumn($dropBilingual);
            }
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['image', 'title', 'description', 'price', 'discount', 'barcode', 'expires_at']);
        });

        if (Schema::hasTable('offers') && Schema::hasIndex('offers', 'offers_status_dates_index')) {
            Schema::table('offers', function (Blueprint $table) {
                $table->dropIndex('offers_status_dates_index');
            });
        }

        Schema::table('offers', function (Blueprint $table) {
            if (Schema::hasColumn('offers', 'description')) {
                $table->renameColumn('description', 'description_ar');
            }
            $cols = ['discount', 'location'];
            if (Schema::hasColumn('offers', 'deleted_at')) {
                $cols[] = 'deleted_at';
            }
            $table->dropColumn($cols);
        });

        if (Schema::hasTable('branches') && Schema::hasColumn('branches', 'mall_id')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropForeign(['mall_id']);
                $table->dropColumn('mall_id');
            });
        }

        if (Schema::hasTable('branches')) {
            Schema::rename('branches', 'store_locations');
        }
    }
};
