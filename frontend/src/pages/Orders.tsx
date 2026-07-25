import { useState, useEffect, useCallback } from "react";
import {
  api,
  type Order,
  type OrderIntent,
  type Decision,
  type Portfolio,
  type Broker,
} from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import {
  Table,
  TableHeader,
  TableBody,
  TableRow,
  TableHead,
  TableCell,
} from "@/components/ui/table";
import { RefreshCw, AlertCircle, ShoppingCart, CheckCircle, XCircle } from "lucide-react";
import { TermTooltip } from "@/components/ui/tooltip";

type Tab = "orders" | "intents" | "decisions";

export default function Orders() {
  const [orders, setOrders] = useState<Order[]>([]);
  const [intents, setIntents] = useState<OrderIntent[]>([]);
  const [decisions, setDecisions] = useState<Decision[]>([]);
  const [portfolios, setPortfolios] = useState<Portfolio[]>([]);
  const [brokers, setBrokers] = useState<Broker[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [tab, setTab] = useState<Tab>("orders");

  const [newOrder, setNewOrder] = useState({
    portfolio_id: "",
    instrument_id: "",
    side: "BUY",
    order_type: "MARKET",
    quantity: "",
    price: "",
  });

  const fetchData = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const [ordRes, intRes, decRes, pfRes, brRes] = await Promise.allSettled([
        api.getPaginated<Order>("/orders?per_page=20"),
        api.getPaginated<OrderIntent>("/order-intents?per_page=20"),
        api.getPaginated<Decision>("/decisions?per_page=20"),
        api.get<Portfolio[]>("/portfolios?per_page=50"),
        api.get<Broker[]>("/brokers?per_page=50"),
      ]);
      if (ordRes.status === "fulfilled") setOrders(ordRes.value.data || []);
      if (intRes.status === "fulfilled") setIntents(intRes.value.data || []);
      if (decRes.status === "fulfilled") setDecisions(decRes.value.data || []);
      if (pfRes.status === "fulfilled") setPortfolios(pfRes.value || []);
      if (brRes.status === "fulfilled") setBrokers(brRes.value || []);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : "Gagal memuat data";
      setError(msg);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  async function submitOrder() {
    setError("");
    try {
      const body: Record<string, string> = {
        portfolio_id: newOrder.portfolio_id,
        instrument_id: newOrder.instrument_id,
        side: newOrder.side,
        order_type: newOrder.order_type,
        quantity: newOrder.quantity,
      };
      if (newOrder.order_type === "LIMIT" && newOrder.price) {
        body.price = newOrder.price;
      }
      await api.post("/orders", body);
      setNewOrder({
        portfolio_id: "",
        instrument_id: "",
        side: "BUY",
        order_type: "MARKET",
        quantity: "",
        price: "",
      });
      fetchData();
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : "Gagal mengirim order";
      setError(msg);
    }
  }

  async function approveIntent(id: string) {
    try {
      await api.post(`/order-intents/${id}/approve`);
      fetchData();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Gagal menyetujui");
    }
  }

  async function rejectIntent(id: string) {
    try {
      await api.post(`/order-intents/${id}/reject`, { reason: "Rejected via UI" });
      fetchData();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Gagal menolak");
    }
  }

  async function cancelOrder(id: string) {
    try {
      await api.post(`/orders/${id}/cancel`, { reason: "Cancelled via UI" });
      fetchData();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Gagal membatalkan");
    }
  }

  const tabs: { key: Tab; label: string }[] = [
    { key: "orders", label: `Order (${orders.length})` },
    { key: "intents", label: `Niat (${intents.length})` },
    { key: "decisions", label: `Keputusan (${decisions.length})` },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Order / OMS</h1>
          <p className="text-sm text-muted-foreground">
            Manajemen order, niat, dan keputusan
          </p>
        </div>
        <Button variant="outline" size="sm" onClick={fetchData} disabled={loading}>
          <RefreshCw className={`mr-2 h-4 w-4 ${loading ? "animate-spin" : ""}`} />
          Muat Ulang
        </Button>
      </div>

      {error && (
        <div className="flex items-center gap-2 rounded-md border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive">
          <AlertCircle className="h-4 w-4 shrink-0" />
          {error}
        </div>
      )}

      {/* New Order Form */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <ShoppingCart className="h-5 w-5" />
            Order Baru
          </CardTitle>
          <CardDescription>Kirim order baru ke sistem trading</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div className="space-y-2">
              <label className="text-sm font-medium">Portofolio</label>
              <Select
                value={newOrder.portfolio_id}
                onChange={(e) => setNewOrder({ ...newOrder, portfolio_id: e.target.value })}
              >
                <option value="">Pilih portofolio...</option>
                {portfolios.map((p) => (
                  <option key={p.portfolio_id} value={p.portfolio_id}>
                    {p.name}
                  </option>
                ))}
              </Select>
            </div>
            <div className="space-y-2">
              <label className="text-sm font-medium">ID Instrumen</label>
              <Input
                placeholder="UUID Instrumen"
                value={newOrder.instrument_id}
                onChange={(e) => setNewOrder({ ...newOrder, instrument_id: e.target.value })}
              />
            </div>
            <div className="space-y-2">
              <label className="text-sm font-medium">Sisi</label>
              <Select
                value={newOrder.side}
                onChange={(e) => setNewOrder({ ...newOrder, side: e.target.value })}
              >
                <option value="BUY">Beli</option>
                <option value="SELL">Jual</option>
              </Select>
            </div>
            <div className="space-y-2">
              <label className="text-sm font-medium">Tipe Order</label>
              <Select
                value={newOrder.order_type}
                onChange={(e) => setNewOrder({ ...newOrder, order_type: e.target.value })}
              >
                <option value="MARKET">Market</option>
                <option value="LIMIT">Limit</option>
              </Select>
            </div>
            <div className="space-y-2">
              <label className="text-sm font-medium">Jumlah</label>
              <Input
                type="number"
                placeholder="100"
                value={newOrder.quantity}
                onChange={(e) => setNewOrder({ ...newOrder, quantity: e.target.value })}
              />
            </div>
            {newOrder.order_type === "LIMIT" && (
              <div className="space-y-2">
                <label className="text-sm font-medium">Harga</label>
                <Input
                  type="number"
                  placeholder="0.00"
                  value={newOrder.price}
                  onChange={(e) => setNewOrder({ ...newOrder, price: e.target.value })}
                />
              </div>
            )}
          </div>
          <div className="mt-4">
            <Button
              onClick={submitOrder}
              disabled={!newOrder.portfolio_id || !newOrder.instrument_id || !newOrder.quantity}
            >
              Kirim Order
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* Tabs */}
      <div className="flex flex-wrap gap-2 border-b border-border pb-2">
        {tabs.map((t) => (
          <button
            key={t.key}
            onClick={() => setTab(t.key)}
            className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
              tab === t.key
                ? "bg-primary text-primary-foreground"
                : "text-muted-foreground hover:bg-accent"
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {/* Tab Content */}
      {tab === "orders" && (
        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>ID Order</TableHead>
                  <TableHead>Sisi</TableHead>
                  <TableHead>Tipe</TableHead>
                  <TableHead>Jumlah</TableHead>
                  <TableHead>Harga</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Aksi</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {orders.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={7} className="text-center text-muted-foreground">
                      Belum ada order
                    </TableCell>
                  </TableRow>
                ) : (
                  orders.map((o) => (
                    <TableRow key={o.order_id}>
                      <TableCell className="font-mono text-xs">
                        {o.order_id.slice(0, 8)}...
                      </TableCell>
                      <TableCell>
                        <Badge variant={o.side === "BUY" ? "success" : "destructive"}>
                          {o.side}
                        </Badge>
                      </TableCell>
                      <TableCell>{o.order_type}</TableCell>
                      <TableCell>{o.quantity}</TableCell>
                      <TableCell>{o.price || "MKT"}</TableCell>
                      <TableCell>
                        <Badge
                          variant={
                            o.status === "FILLED" ? "success" :
                            o.status === "CANCELLED" || o.status === "REJECTED" ? "destructive" :
                            "secondary"
                          }
                        >
                          {o.status}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        {o.status === "PENDING" || o.status === "PARTIALLY_FILLED" ? (
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => cancelOrder(o.order_id)}
                          >
                            Batalkan
                          </Button>
                        ) : (
                          "—"
                        )}
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}

      {tab === "intents" && (
        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>ID Niat</TableHead>
                  <TableHead>Sisi</TableHead>
                  <TableHead>Tipe</TableHead>
                  <TableHead>Jumlah</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Aksi</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {intents.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={6} className="text-center text-muted-foreground">
                      Belum ada niat order
                    </TableCell>
                  </TableRow>
                ) : (
                  intents.map((i) => (
                    <TableRow key={i.intent_id}>
                      <TableCell className="font-mono text-xs">
                        {i.intent_id.slice(0, 8)}...
                      </TableCell>
                      <TableCell>
                        <Badge variant={i.side === "BUY" ? "success" : "destructive"}>
                          {i.side}
                        </Badge>
                      </TableCell>
                      <TableCell>{i.order_type}</TableCell>
                      <TableCell>{i.quantity}</TableCell>
                      <TableCell>
                        <Badge variant="secondary">{i.status}</Badge>
                      </TableCell>
                      <TableCell>
                        {i.status === "PENDING" && (
                          <div className="flex gap-1">
                            <Button variant="ghost" size="sm" onClick={() => approveIntent(i.intent_id)}>
                              <CheckCircle className="h-4 w-4 text-green-500" />
                            </Button>
                            <Button variant="ghost" size="sm" onClick={() => rejectIntent(i.intent_id)}>
                              <XCircle className="h-4 w-4 text-red-500" />
                            </Button>
                          </div>
                        )}
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}

      {tab === "decisions" && (
        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>ID Keputusan</TableHead>
                  <TableHead>Aksi</TableHead>
                  <TableHead>Hasil Kebijakan</TableHead>
                  <TableHead>Status</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {decisions.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={4} className="text-center text-muted-foreground">
                      Belum ada keputusan
                    </TableCell>
                  </TableRow>
                ) : (
                  decisions.map((d) => (
                    <TableRow key={d.decision_id}>
                      <TableCell className="font-mono text-xs">
                        {d.decision_id.slice(0, 8)}...
                      </TableCell>
                      <TableCell>
                        <Badge
                          variant={
                            d.action === "BUY" ? "success" :
                            d.action === "SELL" ? "destructive" : "secondary"
                          }
                        >
                          {d.action}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <Badge variant={d.policy_result === "PASS" ? "success" : "destructive"}>
                          {d.policy_result}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <Badge variant="secondary">{d.status}</Badge>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
