import { useState, type ReactNode } from "react";

type TooltipProps = {
  content: string;
  children: ReactNode;
  className?: string;
};

export function Tooltip({ content, children, className = "" }: TooltipProps) {
  const [show, setShow] = useState(false);

  return (
    <span
      className={`relative inline-flex ${className}`}
      onMouseEnter={() => setShow(true)}
      onMouseLeave={() => setShow(false)}
      onFocus={() => setShow(true)}
      onBlur={() => setShow(false)}
      tabIndex={0}
    >
      <span className="cursor-help border-b border-dashed border-muted-foreground/50 underline decoration-dotted">
        {children}
      </span>
      {show && (
        <span className="absolute bottom-full left-1/2 z-50 mb-2 w-max max-w-xs -translate-x-1/2 rounded-md border border-border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-lg">
          {content}
          <span className="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-border" />
        </span>
      )}
    </span>
  );
}

const GLOSSARY: Record<string, string> = {
  OHLCV: "Open, High, Low, Close, Volume — data harga historis untuk analisis teknikal",
  ETF: "Exchange Traded Fund — reksa dana yang diperdagangkan di bursa seperti saham",
  Sukuk: "Sertifikat wakaf uang — obligasi syariah yang sesuai prinsip Islam",
  ReksaDana: "Wadah pengelolaan dana modal yang digunakan untuk berinvestasi dalam portofolio efek",
  VaR: "Value at Risk — estimasi kerugian maksimum dalam periode tertentu dengan tingkat kepercayaan tertentu",
  Drawdown: "Penurunan nilai portofolio dari titik tertinggi ke titik terendah",
  Sharpe: "Rasio Sharpe — ukuran return disesuaikan risiko (semakin tinggi semakin baik)",
  Sortino: "Rasio Sortino — seperti Sharpe tapi hanya mempertimbangkan volatilitas negatif",
  ROE: "Return on Equity — laba bersih dibagi ekuitas pemegang saham",
  PE: "Price to Earnings — harga saham dibagi laba per saham",
  DebtEquity: "Rasio hutang terhadap ekuitas — ukuran leverage perusahaan",
  Momentum: "Kecepatan perubahan harga — saham dengan momentum positif cenderung naik",
  MeanReversion: "Kecenderungan harga kembali ke rata-rata historisnya",
  Bollinger: "Bollinger Bands — indikator volatilitas menggunakan rata-rata bergerak ± 2 standar deviasi",
  RSI: "Relative Strength Index — indikator momentum (di atas 70 overbought, di bawah 30 oversold)",
  MACD: "Moving Average Convergence Divergence — indikator trend berbasis perbedaan rata-rata bergerak",
  SMA: "Simple Moving Average — rata-rata harga dalam periode tertentu",
  ADX: "Average Directional Index — ukuran kekuatan trend (di atas 25 = trend kuat)",
  Backtest: "Menguji strategi trading menggunakan data historis untuk mengevaluasi performa",
  PaperTrading: "Simulasi trading tanpa uang nyata untuk latihan dan pengujian strategi",
  CompositeScore: "Skor gabungan dari multiple faktor analisis untuk menilai instrumen",
  Screening: "Menyaring instrumen berdasarkan kriteria tertentu (fundamental, teknikal, dll)",
  Regime: "Kondisi pasar saat ini (tren naik, turun, atau sideways)",
  Microstructure: "Analisis struktur pasar mikro (spread, likuiditas, order book)",
  GapRisk: "Risiko pergerakan harga melompat (gap) antara harga tutup dan harga buka",
  Concentration: "Konsentrasi portofolio — seberapa terfokus investasi pada sedikit instrumen",
  Greeks: "Parameter sensitivitas opsi (Delta, Gamma, Theta, Vega, Rho)",
  Swap: "Kontrak derivatif untuk pertukaran arus kas antara dua pihak",
  Repo: "Repurchase Agreement — transaksi jual beli efek dengan kesepakatan beli kembali",
  Crypto: "Mata uang digital terdesentralisasi berbasis blockchain (Bitcoin, Ethereum, dll)",
  Option: "Kontrak yang memberikan hak (bukan kewajiban) untuk membeli/menjual aset pada harga tertentu",
  Volatility: "Ukuran fluktuasi harga — semakin tinggi semakin berisiko",
  Liquidity: "Kemudahan menjual aset tanpa memengaruhi harga secara signifikan",
  Yield: "Tingkat imbal hasil dari investasi (misal: yield obligasi)",
  NAV: "Net Asset Value — nilai aset bersih per unit reksa dana",
};

export function TermTooltip({ term, children }: { term: string; children?: ReactNode }) {
  const explanation = GLOSSARY[term];
  if (!explanation) return <>{children ?? term}</>;
  return <Tooltip content={explanation}>{children ?? term}</Tooltip>;
}
