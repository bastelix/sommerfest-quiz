import type { RENDERER_MATRIX } from './block-renderer-matrix-data.js';

type RendererMatrix = typeof RENDERER_MATRIX;
type VariantsFor<Type extends keyof RendererMatrix> = keyof RendererMatrix[Type];

export type BlockType = keyof RendererMatrix;

export type HeroVariant = VariantsFor<'hero'>;
export type FeatureListVariant = VariantsFor<'feature_list'>;
export type ProcessStepsVariant = VariantsFor<'process_steps'>;
export type TestimonialVariant = VariantsFor<'testimonial'>;
export type RichTextVariant = VariantsFor<'rich_text'>;
export type InfoMediaVariant = VariantsFor<'info_media'>;
export type CtaVariant = VariantsFor<'cta'>;
export type StatStripVariant = VariantsFor<'stat_strip'>;
export type AudienceSpotlightVariant = VariantsFor<'audience_spotlight'>;
export type PackageSummaryVariant = VariantsFor<'package_summary'>;
export type FaqVariant = VariantsFor<'faq'>;
export type SystemModuleVariant = VariantsFor<'system_module'>;
export type CaseShowcaseVariant = VariantsFor<'case_showcase'>;

export type BlockVariant = keyof RendererMatrix[keyof RendererMatrix];

export interface Tokens {
  background?: 'default' | 'muted' | 'primary';
  spacing?: 'small' | 'normal' | 'large';
  width?: 'narrow' | 'normal' | 'wide';
  columns?: 'single' | 'two' | 'three' | 'four';
  accent?: 'brandA' | 'brandB' | 'brandC';
}

export interface Media {
  imageId?: string;
  image?: string;
  alt?: string;
  focalPoint?: { x: number; y: number };
}

export interface CallToAction {
  label: string;
  href: string;
  ariaLabel?: string;
}

export interface HeroBlockData {
  eyebrow?: string;
  headline: string;
  subheadline?: string;
  media?: Media;
  cta: CallToAction;
}

export interface FeatureItem {
  id: string;
  icon?: string;
  title: string;
  description: string;
  bullets?: string[];
  media?: Media;
}

export interface FeatureListBlockData {
  eyebrow?: string;
  title: string;
  subtitle?: string;
  lead?: string;
  intro?: string;
  items: FeatureItem[];
  cta?: CallToAction;
}

export interface ProcessStep {
  id: string;
  title: string;
  description: string;
  duration?: string;
  media?: Media;
}

export interface ClosingCopy {
  title?: string;
  body?: string;
}

export interface ProcessStepsBlockData {
  title: string;
  summary?: string;
  intro?: string;
  steps: ProcessStep[];
  closing?: ClosingCopy;
  ctaPrimary?: CallToAction;
  ctaSecondary?: CallToAction;
}

export interface Author {
  name: string;
  role?: string;
  avatarId?: string;
}

export interface TestimonialBlockData {
  quote: string;
  author: Author;
  source?: string;
}

export interface RichTextBlockData {
  body: string;
  alignment?: 'start' | 'center' | 'end' | 'justify';
}

export interface InfoMediaItem {
  id: string;
  title: string;
  description: string;
  media?: Media;
  bullets?: string[];
}

export interface InfoMediaBlockData {
  title?: string;
  subtitle?: string;
  body?: string;
  items?: InfoMediaItem[];
}

export interface StatMetric {
  id: string;
  value: string;
  label: string;
  asOf?: string;
  tooltip?: string;
  benefit?: string;
}

export interface StatStripBlockData {
  metrics: StatMetric[];
  marquee?: string[];
}

export interface AudienceCase {
  id: string;
  badge?: string;
  title: string;
  lead?: string;
  body?: string;
  bullets?: string[];
  keyFacts?: string[];
  media?: Media;
}

export interface AudienceSpotlightBlockData {
  title: string;
  subtitle?: string;
  cases: AudienceCase[];
}

export interface PackageHighlight {
  title: string;
  bullets?: string[];
}

export interface PackageOption {
  id: string;
  title: string;
  intro?: string;
  highlights?: PackageHighlight[];
}

export interface PackagePlan {
  id: string;
  title: string;
  badge?: string;
  description?: string;
  features?: string[];
  notes?: string[];
  primaryCta?: CallToAction;
  secondaryCta?: CallToAction;
}

export interface PackageSummaryBlockData {
  title: string;
  subtitle?: string;
  options?: PackageOption[];
  plans?: PackagePlan[];
  disclaimer?: string;
}

export interface FaqItem {
  id: string;
  question: string;
  answer: string;
}

export interface FaqFollowUp {
  text?: string;
  linkLabel?: string;
  href?: string;
}

export interface FaqBlockData {
  title: string;
  items: FaqItem[];
  followUp?: FaqFollowUp;
}

export interface BaseBlock<TType extends BlockType, TVariant extends BlockVariant, TData> {
  id: string;
  type: TType;
  variant: TVariant;
  data: TData;
  tokens?: Tokens;
}

export type HeroBlock = BaseBlock<'hero', HeroVariant, HeroBlockData>;
export type FeatureListBlock = BaseBlock<'feature_list', FeatureListVariant, FeatureListBlockData>;
export type ProcessStepsBlock = BaseBlock<'process_steps', ProcessStepsVariant, ProcessStepsBlockData>;
export type TestimonialBlock = BaseBlock<'testimonial', TestimonialVariant, TestimonialBlockData>;
export type RichTextBlock = BaseBlock<'rich_text', RichTextVariant, RichTextBlockData>;
export type InfoMediaBlock = BaseBlock<'info_media', InfoMediaVariant, InfoMediaBlockData>;
export type CtaBlock = BaseBlock<'cta', CtaVariant, CallToAction>;
export type StatStripBlock = BaseBlock<'stat_strip', StatStripVariant, StatStripBlockData>;
export type AudienceSpotlightBlock = BaseBlock<'audience_spotlight', AudienceSpotlightVariant, AudienceSpotlightBlockData>;
export type PackageSummaryBlock = BaseBlock<'package_summary', PackageSummaryVariant, PackageSummaryBlockData>;
export type FaqBlock = BaseBlock<'faq', FaqVariant, FaqBlockData>;
export type SystemModuleBlock = BaseBlock<'system_module', SystemModuleVariant, InfoMediaBlockData>;
export type CaseShowcaseBlock = BaseBlock<'case_showcase', CaseShowcaseVariant, AudienceSpotlightBlockData>;

export type BlockContract =
  | HeroBlock
  | FeatureListBlock
  | ProcessStepsBlock
  | TestimonialBlock
  | RichTextBlock
  | InfoMediaBlock
  | CtaBlock
  | StatStripBlock
  | AudienceSpotlightBlock
  | PackageSummaryBlock
  | FaqBlock
  | SystemModuleBlock
  | CaseShowcaseBlock;
