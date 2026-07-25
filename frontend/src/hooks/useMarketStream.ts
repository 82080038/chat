import { useEffect, useRef, useState } from "react";

export type MarketQuote = {
  symbol: string;
  price: number | null;
  currency: string;
  exchange: string;
  market_time: string | null;
  source: string;
  fetched_at: string;
  cached: boolean;
};

type ConnectionStatus = "connecting" | "open" | "closed" | "error";

export function useMarketStream(symbol: string | null) {
  const [quote, setQuote] = useState<MarketQuote | null>(null);
  const [status, setStatus] = useState<ConnectionStatus>("connecting");
  const [error, setError] = useState<string | null>(null);
  const eventSourceRef = useRef<EventSource | null>(null);

  useEffect(() => {
    if (!symbol) {
      setQuote(null);
      setStatus("closed");
      return;
    }

    const encoded = encodeURIComponent(symbol.toUpperCase());
    const url = `/api/market-data/stream?symbol=${encoded}`;
    const es = new EventSource(url, { withCredentials: true });
    eventSourceRef.current = es;

    setStatus("connecting");
    setError(null);

    es.addEventListener("connected", (event) => {
      try {
        const data = JSON.parse(event.data);
        setStatus("open");
        setError(null);
        console.debug("[useMarketStream] connected", data);
      } catch {
        setStatus("open");
      }
    });

    es.addEventListener("quote", (event) => {
      try {
        const data = JSON.parse(event.data) as MarketQuote;
        setQuote(data);
        setStatus("open");
        setError(null);
      } catch (err) {
        setError("Failed to parse quote");
      }
    });

    es.onerror = () => {
      setStatus("error");
      setError("Stream error or connection lost");
    };

    es.onopen = () => {
      setStatus("open");
      setError(null);
    };

    return () => {
      es.close();
      eventSourceRef.current = null;
    };
  }, [symbol]);

  return { quote, status, error };
}
