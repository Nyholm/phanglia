<?php

namespace Phanglia;

class Socket
{
    /**
     * @var string
     */
    protected $address;

    /**
     * @var array<string => mixed>
     */
    protected $options;

    /**
     * @var resource
     */
    protected $stream;

    /**
     * Constructor
     *
     * @param string $host
     * @param int $port
     * @param array $options
     */
    public function __construct($host = '127.0.0.1', $port = 8649, array $options = array())
    {
        $this->address = 'udp://' . $host . ':' . $port;
        $this->options = array_merge($this->getDefaultOptions(), $options);
    }

    /**
     * @return array<string => mixed>
     */
    protected function getDefaultOptions()
    {
        return array(
            'stream_options'     => array(),
            'stream_params'      => array(),
            'timeout_read_write' => 1
        );
    }

    /**
     * @return resource
     */
    protected function getStreamContext()
    {
        return stream_context_create(
            $this->options['stream_options'],
            $this->options['stream_params']
        );
    }

    /**
     * The PHP Manual warns:
     * UDP sockets will sometimes appear to have opened without an error, even
     * if the remote host is unreachable. The error will only become apparent
     * when you read or write data to/from the socket. The reason for this is
     * because UDP is a "connectionless" protocol, which means that the
     * operating system does not try to establish a link for the socket until
     * it actually needs to send or receive data.
     *
     * @throws ConnectionException
     * @return resource
     */
    protected function getStream()
    {
        if (!$this->stream) {
            $stream = stream_socket_client(
                $this->address,
                $errno,
                $errstr,
                null, // Timeout not used for async connect attempts
                STREAM_CLIENT_ASYNC_CONNECT,
                $this->getStreamContext()
            );

            if (!$stream) {
                throw new ConnectionException('Could not connect: ' . $errstr);
            }

            stream_set_blocking($stream, 0); // Non-blocking
            stream_set_timeout($stream, $this->options['timeout_read_write']);

            $this->stream = $stream;
        }

        return $this->stream;
    }

    /**
     * @param string $payload
     * @return boolean Success
     */
    public function send($payload)
    {
        return @fwrite($this->getStream(), $payload);
    }

    /**
     * Sends the given metric and value(s)
     *
     * @param Metric $metric
     * @param mixed $value
     * @param boolean Success
     */
    public function sendMetric(Metric $metric, $value = null)
    {
        if ($this->send($metric->getMetadataPacket())) {
            if ($value !== null) {
                return $this->send($metric->getValuePacket($value));
            }
        }
        return false;
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if ($this->stream) {
            @fclose($this->stream);
            $this->stream = null;
        }
    }
}