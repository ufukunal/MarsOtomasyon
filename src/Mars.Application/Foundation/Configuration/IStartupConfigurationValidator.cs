namespace Mars.Application.Foundation.Configuration;

public interface IStartupConfigurationValidator<in TOptions>
{
    IReadOnlyList<ConfigurationValidationIssue> Validate(TOptions options);
}
