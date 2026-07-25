import { useMarketStream } from "@/hooks/useMarketStream";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { TrendingUp, TrendingDown, Activity, Wifi, WifiOff, AlertCircle } from "lucide-react";

type LiveQuoteProps = {
  symbol: string;
};

export function LiveQuote({ symbol }: LiveQuoteProps) {
  const { quote, status, error } = useMarketStream(symbol);

  const isUp =
    quote && quote.price !== null ? quote.price >= 0 : false;

  const statusIcon =
    status === "open" ? (
      <Wifi className="h-4 w-4 text-green-500" />
    ) : status === "connecting" ? (
      <Activity className="h-4 w-4 animate-pulse text-yellow-500" />
    ) : (
      <WifiOff className="h-4 w-4 text-red-500" />
    );

  return (
    <Card className="w-full max-w-md">
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle className="text-sm font-medium">{symbol.toUpperCase()}</CardTitle>
        <div className="flex items-center gap-2">
          {statusIcon}
          <Badge variant={status === "open" ? "default" : "secondary"}>
            {status}
          </Badge>
        </div>
      </CardHeader>
      <CardContent>
        {error && (
          <div className="flex items-center gap-2 text-sm text-red-500">
            <AlertCircle className="h-4 w-4" />
            {error}
          </div>
        )}

        {quote && quote.price !== null ? (
          <div className="flex flex-col gap-1">
            <div className="flex items-baseline gap-2">
              <span className="text-3xl font-bold">
                {quote.price.toLocaleString(undefined, {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 4,
                })}
              </span>
              <span className="text-sm text-muted-foreground">
                {quote.currency}
              </span>
              {isUp ? (
                <TrendingUp className="h-5 w-5 text-green-500" />
              ) : (
                <TrendingDown className="h-5 w-5 text-red-500" />
              )}
            </div>
            <div className="text-xs text-muted-foreground">
              {quote.exchange || "—"} · {quote.source} · {new Date(quote.fetched_at).toLocaleTimeString()}
            </div>
          </div>
        ) : (
          <div className="text-sm text-muted-foreground">
            Waiting for first quote…
          </div>
        )}
      </CardContent>
    </Card>
  );
}
