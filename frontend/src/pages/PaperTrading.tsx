import { useState, useEffect, useCallback } from "react";
import { PaperTradingAPI, type PaperOrder, type PaperPosition } from "@/lib/api";
import { ApiError } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from "@/components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { ShoppingCart, Wallet, RefreshCw, X } from "lucide-react";
import { TermTooltip } from "@/components/ui/tooltip";
import { formatRupiah, formatNumber } from "@/lib/format";

export default function PaperTrading() {
  const [orders, setOrders] = useState<PaperOrder[]>([]);
  const [positions, setPositions] = useState<PaperPosition[]>([]);
  const [balance, setBalance] = useState<{ cash_balance: number; available_balance: number; total_value: number } | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [portfolioId, setPortfolioId] = useState("");

  const [form, setForm] = useState({
    instrument_id: "",
    side: "BUY",
    order_type: "MARKET",
    quantity: "100",
    price: "",
  });

  const fetchData = useCallback(async () => {
    if (!portfolioId) return;
    setLoading(true);
    setError(null);
    try {
      const [bal, pos, ord] = await Promise.all([
        PaperTradingAPI.getBalance(portfolioId),
        PaperTradingAPI.listPositions(portfolioId),
        PaperTradingAPI.listOrders(portfolioId, 1, 50),
      ]);
      setBalance(bal);
      setPositions(pos);
      setOrders(ord.data);
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Gagal memuat data");
    } finally {
      setLoading(false);
    }
  }, [portfolioId]);

  useEffect(() => {
    if (portfolioId) fetchData();
  }, [portfolioId, fetchData]);

  const handlePlaceOrder = async () => {
    setError(null);
    try {
      await PaperTradingAPI.placeOrder(portfolioId, {
        instrument_id: form.instrument_id,
        side: form.side,
        order_type: form.order_type,
        quantity: parseInt(form.quantity),
        price: form.order_type === "LIMIT" ? parseFloat(form.price) : undefined,
      });
      setForm({ ...form, instrument_id: "", quantity: "100", price: "" });
      fetchData();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Gagal menempatkan order");
    }
  };

  const handleCancel = async (orderId: string) => {
    try {
      await PaperTradingAPI.cancelOrder(portfolioId, orderId);
      fetchData();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Gagal membatalkan order");
    }
  };

  const fmt = (v: number | string | null, decimals = 2) => {
    return formatNumber(v, decimals);
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Paper Trading</h1>
        <p className="text-sm text-muted-foreground"><TermTooltip term="PaperTrading">Simulasi trading</TermTooltip> dengan modal virtual</p>
      </div>

      <Card>
        <CardContent className="pt-6">
          <div className="flex gap-3">
            <Input
              placeholder="ID Portofolio"
              value={portfolioId}
              onChange={(e) => setPortfolioId(e.target.value)}
              className="max-w-xs"
            />
            <Button variant="outline" onClick={fetchData} disabled={loading || !portfolioId}>
              <RefreshCw className={`mr-2 h-4 w-4 ${loading ? "animate-spin" : ""}`} /> Muat
            </Button>
          </div>
        </CardContent>
      </Card>

      {error && (
        <div className="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
          {error}
        </div>
      )}

      {balance && (
        <div className="grid gap-4 sm:grid-cols-3">
          <Card>
            <CardContent className="pt-6">
              <div className="flex items-center gap-2">
                <Wallet className="h-5 w-5 text-muted-foreground" />
                <span className="text-sm text-muted-foreground">Saldo Kas</span>
              </div>
              <p className="mt-2 text-2xl font-bold">{formatRupiah(balance.cash_balance, 0)}</p>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="pt-6">
              <span className="text-sm text-muted-foreground">Saldo Tersedia</span>
              <p className="mt-2 text-2xl font-bold">{formatRupiah(balance.available_balance, 0)}</p>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="pt-6">
              <span className="text-sm text-muted-foreground">Total Nilai Portofolio</span>
              <p className="mt-2 text-2xl font-bold">{formatRupiah(balance.total_value, 0)}</p>
            </CardContent>
          </Card>
        </div>
      )}

      <Card>
        <CardHeader>
          <CardTitle>Tempatkan Order</CardTitle>
          <CardDescription>Tiket order simulasi</CardDescription>
        </CardHeader>
        <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div className="space-y-1.5">
            <label className="text-sm font-medium">ID Instrumen</label>
            <Input value={form.instrument_id} onChange={(e) => setForm({ ...form, instrument_id: e.target.value })} placeholder="inst_xxx" />
          </div>
          <div className="space-y-1.5">
            <label className="text-sm font-medium">Sisi</label>
            <select className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm" value={form.side} onChange={(e) => setForm({ ...form, side: e.target.value })}>
              <option value="BUY">BELI</option>
              <option value="SELL">JUAL</option>
            </select>
          </div>
          <div className="space-y-1.5">
            <label className="text-sm font-medium">Tipe Order</label>
            <select className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm" value={form.order_type} onChange={(e) => setForm({ ...form, order_type: e.target.value })}>
              <option value="MARKET">MARKET</option>
              <option value="LIMIT">LIMIT</option>
            </select>
          </div>
          <div className="space-y-1.5">
            <label className="text-sm font-medium">Jumlah</label>
            <Input type="number" value={form.quantity} onChange={(e) => setForm({ ...form, quantity: e.target.value })} />
          </div>
          {form.order_type === "LIMIT" && (
            <div className="space-y-1.5">
              <label className="text-sm font-medium">Harga Limit</label>
              <Input type="number" value={form.price} onChange={(e) => setForm({ ...form, price: e.target.value })} />
            </div>
          )}
          <div className="flex items-end">
            <Button onClick={handlePlaceOrder} disabled={!portfolioId || !form.instrument_id}>
              <ShoppingCart className="mr-2 h-4 w-4" /> Tempatkan Order
            </Button>
          </div>
        </CardContent>
      </Card>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Posisi ({positions.length})</CardTitle>
          </CardHeader>
          <CardContent>
            {positions.length === 0 ? (
              <p className="text-sm text-muted-foreground">Belum ada posisi terbuka</p>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Instrumen</TableHead>
                    <TableHead className="text-right">Jumlah</TableHead>
                    <TableHead className="text-right">Biaya Rata-rata</TableHead>
                    <TableHead className="text-right">Nilai Pasar</TableHead>
                    <TableHead className="text-right">PnL</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {positions.map((p) => (
                    <TableRow key={p.position_id}>
                      <TableCell className="font-mono text-xs">{p.instrument_id}</TableCell>
                      <TableCell className="text-right">{p.quantity}</TableCell>
                      <TableCell className="text-right">{fmt(p.average_cost)}</TableCell>
                      <TableCell className="text-right">{fmt(p.market_value, 0)}</TableCell>
                      <TableCell className={`text-right ${p.unrealized_pnl >= 0 ? "text-green-600" : "text-red-600"}`}>
                        {fmt(p.unrealized_pnl, 0)}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Order ({orders.length})</CardTitle>
          </CardHeader>
          <CardContent>
            {orders.length === 0 ? (
              <p className="text-sm text-muted-foreground">Belum ada order</p>
            ) : (
              <div className="max-h-[400px] overflow-y-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Instrumen</TableHead>
                      <TableHead>Sisi</TableHead>
                      <TableHead className="text-right">Jumlah</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead></TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {orders.map((o) => (
                      <TableRow key={o.order_id}>
                        <TableCell className="font-mono text-xs">{o.instrument_id}</TableCell>
                        <TableCell>
                          <Badge variant={o.side === "BUY" ? "default" : "secondary"}>{o.side}</Badge>
                        </TableCell>
                        <TableCell className="text-right">{o.quantity}</TableCell>
                        <TableCell>
                          <Badge variant={o.status === "FILLED" ? "default" : o.status === "OPEN" ? "secondary" : "destructive"}>
                            {o.status}
                          </Badge>
                        </TableCell>
                        <TableCell>
                          {o.status === "OPEN" && (
                            <Button variant="ghost" size="icon" onClick={() => handleCancel(o.order_id)}>
                              <X className="h-4 w-4" />
                            </Button>
                          )}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
