<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remaining legacy ooinuza tables owned by the niblirc stack. Each is
     * guarded so `php artisan migrate` adopts an existing database without
     * re-creating anything. All are static reference/data tables.
     */
    public function up(): void
    {
        if (! Schema::hasTable('anidb')) {
            Schema::create('anidb', function (Blueprint $table) {
                $table->integer('id');
                $table->string('title', 255);
                $table->string('type', 300)->nullable();
                $table->string('lang', 300)->nullable();
            });
        }

        if (! Schema::hasTable('api')) {
            Schema::create('api', function (Blueprint $table) {
                $table->increments('id');
                $table->string('uri', 255);
                $table->string('methodName', 255);
                $table->string('requires', 255)->nullable();
                $table->text('sample')->nullable();
            });
        }

        if (! Schema::hasTable('bot_admins')) {
            Schema::create('bot_admins', function (Blueprint $table) {
                $table->string('user_name', 30);
                $table->string('password', 32);
                $table->unsignedSmallInteger('access');
                $table->smallInteger('loglevel')->default(0);
                $table->primary('user_name');
            });
        }

        if (! Schema::hasTable('channel_topics')) {
            Schema::create('channel_topics', function (Blueprint $table) {
                $table->string('channel', 20);
                $table->string('setBy', 20);
                $table->string('website_trim', 400)->nullable();
                $table->primary('channel');
            });
        }

        if (! Schema::hasTable('newestepisode')) {
            Schema::create('newestepisode', function (Blueprint $table) {
                $table->increments('id');
                $table->string('seriesname', 500)->unique('uniqueseries');
                $table->integer('nextepisode')->default(0);
                $table->tinyInteger('active')->default(1);
            });
        }

        if (! Schema::hasTable('nibl_money')) {
            Schema::create('nibl_money', function (Blueprint $table) {
                $table->string('user_name', 30);
                $table->integer('amount');
                $table->boolean('today_flag');
                $table->primary('user_name');
            });
        }

        if (! Schema::hasTable('nibl_npp')) {
            Schema::create('nibl_npp', function (Blueprint $table) {
                $table->string('user_name', 30);
                $table->integer('npts')->default(0);
                $table->integer('loli')->default(0);
                $table->integer('shta')->default(0);
                $table->integer('trap')->default(0);
                $table->integer('moeh')->default(0);
                $table->integer('cows')->default(0);
                $table->integer('peng')->default(0);
                $table->boolean('n_flag')->default(false);
                $table->boolean('l_flag')->default(false);
                $table->boolean('s_flag')->default(false);
                $table->boolean('t_flag')->default(false);
                $table->boolean('m_flag')->default(false);
                $table->primary('user_name');
            });
        }

        if (! Schema::hasTable('redirect')) {
            Schema::create('redirect', function (Blueprint $table) {
                $table->increments('id');
                $table->text('value');
                $table->text('hash');
                $table->text('username')->nullable();
                $table->timestamp('creation_date')->useCurrent();
            });
        }

        if (! Schema::hasTable('requests')) {
            Schema::create('requests', function (Blueprint $table) {
                $table->increments('id');
                $table->string('in_channel', 20)->nullable();
                $table->string('made_by', 20)->nullable();
                $table->dateTime('made_date')->nullable();
                $table->string('request', 100)->nullable();
                $table->tinyInteger('status_id')->nullable()->index();
                $table->string('filled_by', 20)->nullable();
                $table->dateTime('filled_date')->nullable();
            });
        }

        if (! Schema::hasTable('requests_status')) {
            Schema::create('requests_status', function (Blueprint $table) {
                $table->tinyIncrements('id');
                $table->string('status', 20)->nullable();
            });

            DB::table('requests_status')->insert([
                ['id' => 1, 'status' => 'new'],
                ['id' => 2, 'status' => 'filling'],
                ['id' => 3, 'status' => 'filled'],
                ['id' => 4, 'status' => 'deleted'],
            ]);
        }

        if (! Schema::hasTable('rules')) {
            Schema::create('rules', function (Blueprint $table) {
                $table->increments('id');
                $table->string('channel', 255);
                $table->integer('number');
                $table->text('rule');
            });
        }

        if (! Schema::hasTable('schedules')) {
            Schema::create('schedules', function (Blueprint $table) {
                $table->string('commandName', 50);
                $table->timestamp('lastRun')->useCurrent();
                $table->primary('commandName');
            });
        }

        if (! Schema::hasTable('tweet_channels')) {
            Schema::create('tweet_channels', function (Blueprint $table) {
                $table->increments('id');
                $table->string('channel', 255)->nullable();
                $table->string('twitter_user', 255)->nullable();
                $table->string('twitter_id', 255)->nullable();
            });
        }

        if (! Schema::hasTable('tweets')) {
            Schema::create('tweets', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('channelid')->nullable();
                $table->text('tweetid')->nullable();
                $table->text('tweet_text')->nullable();
            });
        }

        if (! Schema::hasTable('words')) {
            Schema::create('words', function (Blueprint $table) {
                $table->increments('id');
                $table->text('value');
                $table->integer('type');
                $table->boolean('dirtybit')->default(false);
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'anidb', 'api', 'bot_admins', 'channel_topics', 'newestepisode',
            'nibl_money', 'nibl_npp', 'redirect', 'requests', 'requests_status',
            'rules', 'schedules', 'tweet_channels', 'tweets', 'words',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
