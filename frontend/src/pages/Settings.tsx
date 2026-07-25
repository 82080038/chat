import { useState, useEffect, useCallback } from "react";
import { api, type ConfigEntry, type Owner } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
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
import { Settings as SettingsIcon, RefreshCw, AlertCircle, User } from "lucide-react";

export default function Settings() {
  const { owner } = useAuth();
  const [configs, setConfigs] = useState<ConfigEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [search, setSearch] = useState("");

  const fetchData = useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const { data } = await api.getPaginated<ConfigEntry>("/configurations?per_page=50");
      setConfigs(data || []);
    } catch {
      setConfigs([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const filteredConfigs = configs.filter(
    (c) =>
      !search ||
      c.key.toLowerCase().includes(search.toLowerCase()) ||
      c.category.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Settings</h1>
          <p className="text-sm text-muted-foreground">
            Account and configuration management
          </p>
        </div>
        <Button variant="outline" size="sm" onClick={fetchData} disabled={loading}>
          <RefreshCw className={`mr-2 h-4 w-4 ${loading ? "animate-spin" : ""}`} />
          Refresh
        </Button>
      </div>

      {error && (
        <div className="flex items-center gap-2 rounded-md border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive">
          <AlertCircle className="h-4 w-4 shrink-0" />
          {error}
        </div>
      )}

      {/* Account Info */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <User className="h-5 w-5" />
            Account
          </CardTitle>
          <CardDescription>Owner account information</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
              <p className="text-sm text-muted-foreground">Email</p>
              <p className="font-medium">{owner?.email || "—"}</p>
            </div>
            <div>
              <p className="text-sm text-muted-foreground">Display Name</p>
              <p className="font-medium">{owner?.display_name || "—"}</p>
            </div>
            <div>
              <p className="text-sm text-muted-foreground">Owner ID</p>
              <p className="font-mono text-xs">{owner?.owner_id || "—"}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Config Entries */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <SettingsIcon className="h-5 w-5" />
            Configuration
          </CardTitle>
          <CardDescription>Platform configuration entries</CardDescription>
        </CardHeader>
        <CardContent>
          <Input
            placeholder="Search config by key or category..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="mb-4"
          />
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Key</TableHead>
                <TableHead>Value</TableHead>
                <TableHead>Category</TableHead>
                <TableHead>Status</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {filteredConfigs.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={4} className="text-center text-muted-foreground">
                    No configuration entries
                  </TableCell>
                </TableRow>
              ) : (
                filteredConfigs.map((c) => (
                  <TableRow key={c.config_id}>
                    <TableCell className="font-medium">{c.key}</TableCell>
                    <TableCell className="max-w-xs truncate font-mono text-xs">
                      {c.value}
                    </TableCell>
                    <TableCell>
                      <Badge variant="secondary">{c.category}</Badge>
                    </TableCell>
                    <TableCell>
                      <Badge variant={c.status === "ACTIVE" ? "success" : "outline"}>
                        {c.status}
                      </Badge>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}
