<?php namespace Haruncpi\QueryLog;

use Haruncpi\QueryLog\Supports\JsonLogFileWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * QueryLog Class
 * @since 1.0.0
 */
class QueryLog
{
    const FORMAT_JSON = 'json';

    /**
     * @var string file name for query log inside storage directory
     */
    private $file_name = 'query.log';

    /**
     * @var string file format of query log. default is text. available text,json
     */
    private $format;

    /**
     * @var string $file_path use for get the absolute path
     */
    private $file_path;

    /**
     * @var int calculate the total number of query.
     */
    private $total_query;

    /**
     * @var int calculate the total amount of query time.
     */
    private $total_time;

    /**
     * @var array file data for writing data into file.
     */
    private $final;


    /**
     * QueryLog constructor.
     * @throws \Exception
     */
    public function __construct()
    {
        $this->file_path = $this->filePath();
        $this->format = trim(env('QUERY_LOG_FORMAT', self::FORMAT_JSON));
        $this->total_query = 0;
        $this->total_time = 0;
        $this->final = [];

        if (!in_array(strtolower($this->format), [self::FORMAT_JSON])) {
            throw new \Exception('Invalid query log data file format. Support text or json file format.');
        }
        
        $this->listenQueries();
    }

    public function filePath()
    {
        $baseDir = storage_path('logs/' .config('query-log.log_level', 'info'). '/' .date('Y-m'). '//data_base_queries/');
        if (!File::exists($baseDir)) {
            File::makeDirectory($baseDir, 0777, true);
        }
        
        return $baseDir . '/' . date('Y-m-d') . '.log';
    }

    /**
     * Query listener
     * @return void
     *
     * @since 1.0.0
     */
    private function listenQueries()
    {
        DB::listen(function ($query) {
            $this->total_query++;
            $this->total_time += $query->time;

            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            foreach ($backtrace as $trace) {
                if (array_key_exists('file', $trace) && array_key_exists('line', $trace)) {
                    if (strpos($trace['file'], base_path('app')) !== false) {
                        $this->addQuery($query, $trace);
                        break;
                    }
                }
            }
        });

        app()->terminating(function () {

            $this->final['meta_data'][] = config('query-log.meta_data');
            $this->final['meta_data'][] = [
                // Request
                'url' => request()->fullUrl(),
                'method' => request()->method(),
                // Client
                'client_ip' => request()->ip(),
                'user_agent' => request()->header('User-Agent'),
                'authenticated_user' => optional(request()->user())->only(['id', 'email'])
            ];
            

            if ($this->format == self::FORMAT_JSON && !empty($this->final['queries'])) {
                (new JsonLogFileWriter)->write($this->file_path, $this->final);
            }
        });
    }

    /**
     * Make final query from sql bindings
     *
     * @param $query
     * @return string
     *
     * @since 1.0.0
     */
    private function getSqlWithBindings($query)
    {
        return vsprintf(str_replace('?', '%s', $query->sql), collect($query->bindings)
            ->map(function ($binding) {
                return is_numeric($binding) ? $binding : "'{$binding}'";
            })->toArray());
    }

    /**
     * add each query in a specific array format
     *
     * @param object $query
     * @param array $trace
     * @return void
     *
     * @since 1.0.0
     */
    private function addQuery($query, $trace)
    {
        $line = $trace['line'];

        $this->final['timestamp'] = gmdate('c');
        $this->final['total_query'] = $this->total_query;
        $this->final['total_time']  = $this->total_time;

        $this->final['queries'][] = [
            'sl' => $this->total_query,
            'query' => $query->sql,
            'bindings' => $query->bindings,
            'final_query' => $this->getSqlWithBindings($query),
            'time' => $query->time,
            'time_precise' => round($query->time, 3),
            'file' => $trace['file'] . ":$line",
            'line' => $line,
            'connection' => $query->connectionName,
            'environment' => app()->environment(),
        ];
    }
}
