<?php

declare(strict_types=1);

namespace BeycanPress\FreshBooks;

final class HttpClient
{
    /**
     * Base API url
     * @var string|null
     */
    private ?string $baseUrl = null;

    /**
     * cURL process infos
     * @var mixed
     */
    private mixed $info;

    /**
     * cURL process errors
     * @var string
     */
    private string $error;

    /**
     * @var array<string>
     */
    private array $methods = [
        "GET",
        "HEAD",
        "POST",
        "PUT",
        "DELETE",
        "CONNECT",
        "OPTIONS",
        "TRACE",
        "PATCH",
    ];

    /**
     * @var array<string>
     */
    private array $headers = [];

    /**
     * Default options
     * @var array<int,mixed>
     */
    private array $options = [
        CURLOPT_RETURNTRANSFER => true,
    ];

    /**
     * @param string $url
     * @return HttpClient
     */
    public function setBaseUrl(string $url): HttpClient
    {
        $this->baseUrl = $url;
        return $this;
    }

    /**
     * @param int $key
     * @param mixed $value
     * @return HttpClient
     */
    public function addOption(int $key, mixed $value): HttpClient
    {
        $this->options[$key] = $value;
        return $this;
    }

    /**
     * @param int $key
     * @return HttpClient
     */
    public function deleteOption(int $key): HttpClient
    {
        if (isset($this->options[$key])) {
            unset($this->options[$key]);
        }
        return $this;
    }

    /**
     * @param array<int> $keys
     * @return HttpClient
     */
    public function deleteOptions(array $keys): HttpClient
    {
        foreach ($keys as $key) {
            $this->deleteOption($key);
        }
        return $this;
    }

    /**
     * @param array<int,mixed> $options
     * @return HttpClient
     */
    public function addOptions(array $options): HttpClient
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    /**
     * @param string $key
     * @param string $value
     * @return HttpClient
     */
    public function addHeader(string $key, string $value): HttpClient
    {
        $this->headers[$key] = $key . ': ' . $value;
        return $this;
    }

    /**
     * @param string $key
     * @return HttpClient
     */
    public function deleteHeader(string $key): HttpClient
    {
        if (isset($this->headers[$key])) {
            unset($this->headers[$key]);
        }
        return $this;
    }

    /**
     * @param array<string,string> $headers
     * @return HttpClient
     */
    public function addHeaders(array $headers): HttpClient
    {
        foreach ($headers as $key => $value) {
            $this->addHeader($key, $value);
        }

        return $this;
    }

    /**
     * @param array<string> $keys
     * @return HttpClient
     */
    public function deleteHeaders(array $keys): HttpClient
    {
        foreach ($keys as $key) {
            $this->deleteHeader($key);
        }
        return $this;
    }

    /**
     * @return mixed
     */
    public function getInfo(): mixed
    {
        return $this->info;
    }

    /**
     * @return string
     */
    public function getError(): string
    {
        return $this->error;
    }

    /**
     *
     * @param string $string
     * @return mixed
     */
    private function ifIsJson(string $string): mixed
    {
        $json = json_decode($string);
        if (JSON_ERROR_NONE === json_last_error()) {
            return $json;
        } else {
            return $string;
        }
    }

    /**
     * @param string $name
     * @param array<mixed> $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (!in_array(strtoupper($name), $this->methods)) {
            throw new \Exception("Method not found");
        }

        $this->addOption(CURLOPT_CUSTOMREQUEST, strtoupper($name));
        $this->addOption(CURLOPT_HTTPHEADER, array_values($this->headers));
        return $this->beforeSend(...$arguments);
    }

    /**
     * @return array<string>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * @param string $url
     * @param array<mixed> $data
     * @param boolean $raw
     * @return mixed
     */
    private function beforeSend(string $url, array $data = [], bool $raw = false): mixed
    {
        if (!empty($data)) {
            if ($raw) {
                $data = json_encode($data);
                $data = <<<DATA
                    $data
                DATA;
            } else {
                $data = http_build_query($data);
            }
            $this->addOption(CURLOPT_POSTFIELDS, $data);
        }

        return $this->send($url);
    }

    /**
     * @param string $url
     * @return mixed
     */
    private function send(string $url): mixed
    {
        if (!filter_var($url, FILTER_VALIDATE_URL) && !is_null($this->baseUrl)) {
            $url = $this->baseUrl . $url;
        }

        // Init
        $curl = curl_init($url);

        // Set options
        curl_setopt_array($curl, $this->options);

        // Exec
        $result = curl_exec($curl);

        // Get some information
        $this->info = curl_getinfo($curl);
        $this->error = curl_error($curl);

        // Close
        curl_close($curl);

        if (is_string($result)) {
            $result = $this->ifIsJson($result);
        }

        $this->deleteOption(CURLOPT_POSTFIELDS);

        return $result;
    }
}
