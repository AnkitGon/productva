import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, Check, Circle, PartyPopper, Sparkles } from 'lucide-react';
import { useState } from 'react';

import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';

interface ReadinessItem {
    key: string;
    title: string;
    why: string;
    href: string | null;
    done: boolean;
    metric: string;
    metric_detail: string | null;
    warnings: string[];
    hint: string | null;
    required: boolean;
}

interface Problem {
    severity: string;
    href: string | null;
}

interface FactoryHealth {
    overall: number;
    inventory: number;
    manufacturing: number;
    resources: number;
    configuration: number;
}

interface Roadmap {
    percent_ready: number;
    ready: boolean;
    required_done: number;
    required_total: number;
    next_required: { key: string; title: string; href: string | null } | null;
    dismissed: boolean;
    required: ReadinessItem[];
    optional: ReadinessItem[];
    problems: Problem[];
    factory_health: FactoryHealth;
    go_live: {
        title: string;
        subtitle: string;
        next_steps: string[];
    };
    daily_operations: string[];
    future_modules: string[];
}

interface Props {
    roadmap: Roadmap;
    userName: string;
    organizationName: string | null;
    activePlantName: string | null;
}

function StatusIcon({ done }: { done: boolean }) {
    if (done) {
        return (
            <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white">
                <Check className="size-3.5" strokeWidth={3} />
            </span>
        );
    }

    return (
        <span className="flex size-6 shrink-0 items-center justify-center rounded-full border border-border text-muted-foreground">
            <Circle className="size-3" />
        </span>
    );
}

function HealthBar({ label, value }: { label: string; value: number }) {
    return (
        <div className="space-y-1.5">
            <div className="flex items-center justify-between text-sm">
                <span className="text-muted-foreground">{label}</span>
                <span className="font-semibold tabular-nums">{value}%</span>
            </div>
            <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                <div className="h-full rounded-full bg-sky-600" style={{ width: `${value}%` }} />
            </div>
        </div>
    );
}

function ItemRow({ item }: { item: ReadinessItem }) {
    const body = (
        <div className="flex items-start gap-3 rounded-xl border border-border/50 bg-card px-4 py-3 transition hover:border-sky-300 hover:bg-sky-50/40">
            <StatusIcon done={item.done} />
            <div className="min-w-0 flex-1 space-y-1">
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                    <p className="font-medium">{item.title}</p>
                    <p className="font-mono text-sm font-semibold tabular-nums text-foreground">{item.metric}</p>
                </div>
                <p className="text-sm text-muted-foreground">{item.why}</p>
                {item.metric_detail && (
                    <p className="text-xs text-muted-foreground">{item.metric_detail}</p>
                )}
                {item.hint && <p className="text-xs text-sky-800">{item.hint}</p>}
                {item.warnings.length > 0 && (
                    <ul className="space-y-0.5 pt-1">
                        {item.warnings.map((warning) => (
                            <li key={warning} className="text-xs text-amber-700">
                                ⚠ {warning}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
            {item.href && <ArrowRight className="mt-1 size-4 shrink-0 text-muted-foreground" />}
        </div>
    );

    return item.href ? <Link href={item.href}>{body}</Link> : body;
}

export default function GettingStarted({ roadmap, userName, organizationName, activePlantName }: Props) {
    const dismissForm = useForm({});
    const [confirmCompleteOpen, setConfirmCompleteOpen] = useState(false);
    const firstName = userName.split(' ')[0] || userName;
    const canGoLive = roadmap.ready;

    const markCompleted = () => {
        dismissForm.post('/setup/dismiss', {
            onFinish: () => setConfirmCompleteOpen(false),
        });
    };

    return (
        <>
            <Head title="Production Readiness" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-8 p-6 md:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-3 min-w-0 flex-1">
                        <div className="inline-flex items-center gap-2 rounded-full bg-sky-100 px-3 py-1 text-xs font-medium text-sky-800">
                            <Sparkles className="size-3.5" />
                            Getting Started
                        </div>
                        <h1 className="text-3xl font-semibold tracking-tight">Welcome, {firstName}</h1>
                        <p className="max-w-2xl text-muted-foreground leading-relaxed">
                            Production readiness for{' '}
                            <span className="font-medium text-foreground">{organizationName ?? 'your company'}</span>
                            {activePlantName ? (
                                <>
                                    {' '}
                                    · <span className="font-medium text-foreground">{activePlantName}</span>
                                </>
                            ) : null}
                            . This is a roadmap to help you configure your factory. Open each module to configure your factory.
                        </p>
                    </div>
                    {!roadmap.dismissed ? (
                        <Button
                            className="shrink-0"
                            disabled={dismissForm.processing}
                            onClick={() => setConfirmCompleteOpen(true)}
                        >
                            Mark Completed
                        </Button>
                    ) : (
                        <div className="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1.5 text-xs font-medium text-emerald-800 shrink-0">
                            <Check className="size-3.5" />
                            Marked complete
                        </div>
                    )}
                </div>

                <div className="rounded-2xl border border-border/60 bg-card p-5 md:p-6 space-y-5">
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p className="text-xs uppercase tracking-[0.18em] text-muted-foreground">Production Readiness</p>
                            <p className="mt-1 text-3xl font-semibold tabular-nums">{roadmap.percent_ready}% Ready</p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {roadmap.required_done} of {roadmap.required_total} required items ready
                            </p>
                        </div>
                        {roadmap.next_required?.href ? (
                            <div className="text-right space-y-2">
                                <p className="text-xs uppercase tracking-wide text-muted-foreground">Continue Setup</p>
                                <Button asChild>
                                    <Link href={roadmap.next_required.href}>
                                        Next Required Step
                                        <span className="mx-2 text-primary-foreground/70">·</span>
                                        {roadmap.next_required.title}
                                        <ArrowRight className="ml-2 size-4" />
                                    </Link>
                                </Button>
                            </div>
                        ) : canGoLive ? (
                            <div className="inline-flex items-center gap-2 text-sm font-medium text-emerald-700">
                                <PartyPopper className="size-4" />
                                Ready for production
                            </div>
                        ) : null}
                    </div>
                    <div className="h-2.5 overflow-hidden rounded-full bg-muted">
                        <div
                            className="h-full rounded-full bg-sky-600 transition-all"
                            style={{ width: `${roadmap.percent_ready}%` }}
                        />
                    </div>
                </div>

                <section className="rounded-2xl border border-border/60 bg-card p-5 md:p-6 space-y-4">
                    <div>
                        <h2 className="text-lg font-semibold tracking-tight">Factory Health</h2>
                        <p className="text-sm text-muted-foreground">Managers can monitor readiness by area.</p>
                    </div>
                    <p className="text-3xl font-semibold tabular-nums">{roadmap.factory_health.overall}%</p>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <HealthBar label="Inventory" value={roadmap.factory_health.inventory} />
                        <HealthBar label="Manufacturing" value={roadmap.factory_health.manufacturing} />
                        <HealthBar label="Resources" value={roadmap.factory_health.resources} />
                        <HealthBar label="Configuration" value={roadmap.factory_health.configuration} />
                    </div>
                </section>

                {roadmap.problems.length > 0 && (
                    <section className="rounded-2xl border border-rose-200 bg-rose-50/70 p-5 md:p-6 space-y-4">
                        <div className="flex items-start gap-3">
                            <AlertTriangle className="mt-0.5 size-5 text-rose-700" />
                            <div>
                                <h2 className="text-lg font-semibold tracking-tight text-rose-950">You cannot start production yet.</h2>
                                <p className="text-sm text-rose-800">Problems found — fix these before going live.</p>
                            </div>
                        </div>
                        <ul className="space-y-2">
                            {roadmap.problems.map((problem) => {
                                const row = (
                                    <div className="flex items-center gap-2 rounded-lg border border-rose-200/80 bg-white/70 px-3 py-2.5 text-sm text-rose-950">
                                        <span>❌</span>
                                        <span className="flex-1 font-medium">{problem.severity}</span>
                                        {problem.href && <ArrowRight className="size-4 text-rose-400" />}
                                    </div>
                                );

                                return (
                                    <li key={problem.severity}>
                                        {problem.href ? <Link href={problem.href}>{row}</Link> : row}
                                    </li>
                                );
                            })}
                        </ul>
                    </section>
                )}

                {canGoLive && (
                    <section className="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-5 md:p-6 space-y-4">
                        <div className="flex items-start gap-3">
                            <PartyPopper className="mt-0.5 size-5 text-emerald-700" />
                            <div>
                                <h2 className="text-lg font-semibold tracking-tight text-emerald-950">
                                    🎉 {roadmap.go_live.title}
                                </h2>
                                <p className="text-sm text-emerald-800">{roadmap.go_live.subtitle}</p>
                            </div>
                        </div>
                        <ul className="space-y-2">
                            {roadmap.go_live.next_steps.map((step) => (
                                <li key={step} className="flex items-center gap-2 text-sm text-emerald-950">
                                    <Check className="size-4 text-emerald-700" />
                                    {step}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <section className="space-y-4">
                    <div>
                        <h2 className="text-lg font-semibold tracking-tight">Required Before Go Live</h2>
                        <p className="text-sm text-muted-foreground">Complete these today to operate the factory.</p>
                    </div>
                    <div className="space-y-3">
                        {roadmap.required.map((item) => (
                            <ItemRow key={item.key} item={item} />
                        ))}
                    </div>
                </section>

                <section className="space-y-4">
                    <div>
                        <h2 className="text-lg font-semibold tracking-tight">Optional Enhancements</h2>
                        <p className="text-sm text-muted-foreground">Helpful, but not blocking go-live.</p>
                    </div>
                    <div className="space-y-3">
                        {roadmap.optional.map((item) => (
                            <ItemRow key={item.key} item={item} />
                        ))}
                    </div>
                </section>

                <section className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-2xl border border-dashed border-border/70 bg-muted/20 p-5">
                        <h2 className="font-semibold tracking-tight">Daily Operations (After Setup)</h2>
                        <ol className="mt-4 space-y-2 text-sm text-muted-foreground">
                            {roadmap.daily_operations.map((step, i) => (
                                <li key={step}>
                                    {i + 1}. {step}
                                </li>
                            ))}
                        </ol>
                    </div>
                    <div className="rounded-2xl border border-dashed border-border/70 bg-muted/20 p-5">
                        <h2 className="font-semibold tracking-tight">Future Modules</h2>
                        <ul className="mt-4 flex flex-wrap gap-2">
                            {roadmap.future_modules.map((mod) => (
                                <li
                                    key={mod}
                                    className="rounded-full border border-border/60 bg-background px-2.5 py-1 text-xs text-muted-foreground"
                                >
                                    {mod}
                                </li>
                            ))}
                        </ul>
                    </div>
                </section>

                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border/50 pt-4">
                    <Button variant="outline" asChild>
                        <Link href="/dashboard">Go to dashboard</Link>
                    </Button>
                </div>
            </div>

            <ConfirmDeleteDialog
                open={confirmCompleteOpen}
                onOpenChange={setConfirmCompleteOpen}
                title="Mark Getting Started complete?"
                description={
                    <>
                        This action cannot be undone. The Getting Started guide will be marked completed and you will
                        never see it again in the sidebar.
                    </>
                }
                confirmLabel="Mark Completed"
                onConfirm={markCompleted}
                processing={dismissForm.processing}
            />
        </>
    );
}

GettingStarted.layout = (page: React.ReactNode) => (
    <AppLayout
        breadcrumbs={[
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Getting Started', href: '/setup' },
        ]}
    >
        {page}
    </AppLayout>
);
